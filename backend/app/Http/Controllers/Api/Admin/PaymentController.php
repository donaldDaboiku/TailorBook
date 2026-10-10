<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $payments = SubscriptionPayment::query()
            ->with(['user.business'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference', 'ilike', "%{$search}%")
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('name', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%")
                                ->orWhereHas('business', function ($business) use ($search) {
                                    $business->where('name', 'ilike', "%{$search}%");
                                });
                        });
                });
            })
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (SubscriptionPayment $payment) => $this->paymentPayload($payment))
            ->values();

        return response()->json([
            'data' => $payments,
        ]);
    }

    public function forShop(User $user): JsonResponse
    {
        abort_unless($user->role === UserRole::Tailor, 404);

        $payments = SubscriptionPayment::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (SubscriptionPayment $payment) => $this->paymentPayload($payment, false))
            ->values();

        return response()->json([
            'data' => $payments,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function paymentPayload(SubscriptionPayment $payment, bool $withShop = true): array
    {
        $payload = [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'amount' => (int) $payment->amount,
            'amount_label' => $payment->amountLabel(),
            'currency' => $payment->currency,
            'channel' => $payment->channel,
            'status' => $payment->status,
            'gateway_response' => $payment->gateway_response,
            'failure_message' => $payment->failure_message,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'refunded_at' => $payment->refunded_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];

        if ($withShop) {
            $payload['user_id'] = $payment->user_id;
            $payload['owner_name'] = $payment->user?->name;
            $payload['email'] = $payment->user?->email;
            $payload['shop_name'] = $payment->user?->business?->name;
        }

        return $payload;
    }
}
