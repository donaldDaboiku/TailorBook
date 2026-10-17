<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\FeedbackMessage;
use App\Models\User;
use App\Services\PaystackBilling;
use App\Services\PhoneNormalizer;
use App\Support\NigerianStates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;

class PublicController extends Controller
{
    public function presence(PaystackBilling $billing): JsonResponse
    {
        $subscribers = User::query()
            ->where('role', UserRole::Tailor)
            ->whereNull('suspended_at')
            ->where(function ($query) {
                $query->where('subscription_status', SubscriptionStatus::Free)
                    ->orWhere(function ($paid) {
                        $paid->where('subscription_status', SubscriptionStatus::Subscribed)
                            ->whereDate('subscribed_until', '>=', now()->toDateString());
                    });
            })
            ->with('business:id,user_id,state')
            ->get();

        $states = $subscribers
            ->groupBy(fn (User $user) => $user->business?->state)
            ->filter(fn ($group, $state) => is_string($state) && $state !== '')
            ->map(fn ($group, string $state) => [
                'state' => $state,
                'subscribers' => $group->count(),
            ])
            ->sortByDesc('subscribers')
            ->values();

        $plan = $billing->plan();

        return response()->json([
            'data' => [
                'subscribers' => $subscribers->count(),
                'states' => $states,
                'plan' => [
                    'label' => $plan['label'],
                    'amount_label' => $plan['amount_label'],
                    'days' => $plan['days'],
                ],
                'state_options' => NigerianStates::all(),
                'contact' => $this->contact(),
                'social' => $this->social(),
            ],
        ]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $userId = $this->optionalUserId($request);
        $name = trim($data['name']);
        $contact = trim($data['contact']);
        $body = trim($data['message']);

        FeedbackMessage::query()->create([
            'user_id' => $userId,
            'name' => $name,
            'contact' => $contact,
            'message' => $body,
        ]);

        $this->emailAdmin($name, $contact, $body);

        return response()->json([
            'message' => 'The TailorMate admin received your message.',
        ], 201);
    }

    /**
     * @return array{email: ?string, phone: ?string, whatsapp_url: ?string, sms_url: ?string}
     */
    private function contact(): array
    {
        $email = $this->filledConfig('services.support.email');
        $phone = $this->filledConfig('services.support.phone');
        $whatsapp = $this->filledConfig('services.support.whatsapp') ?? $phone;
        $whatsappUrl = null;

        if ($whatsapp !== null) {
            $whatsappUrl = str_starts_with($whatsapp, 'http')
                ? $whatsapp
                : PhoneNormalizer::whatsappUrl($whatsapp, 'NG', 'Hello TailorMate');
        }

        return [
            'email' => $email,
            'phone' => $phone,
            'whatsapp_url' => $whatsappUrl,
            'sms_url' => $phone === null ? null : PhoneNormalizer::smsUrl($phone),
        ];
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private function social(): array
    {
        $links = [];

        foreach ([
            'Instagram' => 'services.support.instagram',
            'Facebook' => 'services.support.facebook',
            'TikTok' => 'services.support.tiktok',
            'X' => 'services.support.x',
        ] as $name => $key) {
            $url = $this->filledConfig($key);

            if ($url !== null && str_starts_with($url, 'https://')) {
                $links[] = ['name' => $name, 'url' => $url];
            }
        }

        return $links;
    }

    private function emailAdmin(string $name, string $contact, string $body): void
    {
        $adminEmail = $this->filledConfig('services.support.email');

        if ($adminEmail === null || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            Mail::raw(
                "App feedback for TailorMate\n\nFrom: {$name}\nReach them at: {$contact}\n\n{$body}",
                function ($message) use ($adminEmail, $name) {
                    $message->to($adminEmail)->subject("TailorMate app feedback from {$name}");
                },
            );
        } catch (\Throwable $exception) {
            Log::warning('App feedback was saved, but the admin email failed.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function filledConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function optionalUserId(Request $request): ?int
    {
        $bearer = $request->bearerToken();

        if (! is_string($bearer) || $bearer === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearer);
        $user = $token?->tokenable;

        return $user instanceof User ? $user->id : null;
    }
}
