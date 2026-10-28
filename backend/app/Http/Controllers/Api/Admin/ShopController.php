<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetShopPasswordRequest;
use App\Http\Requests\Admin\UpdateShopStateRequest;
use App\Http\Requests\Admin\UpdateShopSubscriptionRequest;
use App\Models\Business;
use App\Models\CatalogueItem;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\PaystackBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('role', UserRole::Tailor)
            ->with('business')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhereHas('business', function ($business) use ($search) {
                            $business->where('name', 'ilike', "%{$search}%")
                                ->orWhere('phone', 'ilike', "%{$search}%")
                                ->orWhere('state', 'ilike', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $paymentsByUser = SubscriptionPayment::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->take(5)->values());

        $businessIds = $users->map(fn (User $user) => $user->business?->id)->filter()->values();
        $usedByBusiness = CatalogueItem::query()
            ->whereIn('business_id', $businessIds)
            ->selectRaw('business_id, count(*) as aggregate')
            ->groupBy('business_id')
            ->pluck('aggregate', 'business_id');

        $shops = $users
            ->map(function (User $user) use ($paymentsByUser, $usedByBusiness) {
                $user->setRelation(
                    'subscriptionPayments',
                    $paymentsByUser->get($user->id, collect()),
                );

                $used = $user->business
                    ? (int) $usedByBusiness->get($user->business->id, 0)
                    : 0;

                return $this->shopPayload($user, $used);
            })
            ->values();

        return response()->json([
            'data' => $shops,
            'catalogue' => $this->catalogueOffer(),
        ]);
    }

    public function suspend(User $user): JsonResponse
    {
        abort_unless($user->role === UserRole::Tailor, 404);

        $user->forceFill(['suspended_at' => now()])->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Shop suspended.',
            'data' => $this->shopPayload($user->fresh()->load('business')),
        ]);
    }

    public function unsuspend(User $user): JsonResponse
    {
        abort_unless($user->role === UserRole::Tailor, 404);

        $user->forceFill(['suspended_at' => null])->save();

        return response()->json([
            'message' => 'Shop activated.',
            'data' => $this->shopPayload($user->fresh()->load('business')),
        ]);
    }

    public function updateState(UpdateShopStateRequest $request, User $user): JsonResponse
    {
        abort_unless($user->role === UserRole::Tailor, 404);

        $business = $user->business;
        abort_unless($business instanceof Business, 422, 'This account has no shop yet.');

        $business->update([
            'state' => $request->validated('state'),
        ]);

        return response()->json([
            'message' => 'State updated.',
            'data' => $this->shopPayload($user->fresh()->load('business')),
        ]);
    }

    public function updateSubscription(
        UpdateShopSubscriptionRequest $request,
        User $user,
    ): JsonResponse {
        abort_unless($user->role === UserRole::Tailor, 404);

        $status = SubscriptionStatus::from($request->validated('subscription_status'));
        $until = $request->validated('subscribed_until');

        $user->forceFill([
            'subscription_status' => $status,
            'subscribed_until' => $status === SubscriptionStatus::Free ? null : $until,
        ])->save();

        if (! $user->fresh()->hasSubscriptionAccess()) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Subscription updated.',
            'data' => $this->shopPayload($user->fresh()->load('business')),
        ]);
    }

    public function updateCatalogue(Request $request, User $user): JsonResponse
    {
        abort_unless($user->role === UserRole::Tailor, 404);

        $data = $request->validate([
            'extra' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        $business = $user->business;
        abort_unless($business instanceof Business, 422, 'This account has no shop yet.');

        $business->forceFill([
            'catalogue_extra' => (int) $data['extra'],
        ])->save();

        return response()->json([
            'message' => 'Catalogue allowance updated.',
            'data' => $this->shopPayload($user->fresh()->load('business')),
        ]);
    }

    public function updateCataloguePrice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'free' => ['required', 'integer', 'min:0', 'max:500'],
            'pack' => ['required', 'integer', 'min:1', 'max:500'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'catalogue_free'],
            ['value' => (string) ((int) $data['free'])],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'catalogue_pack_slots'],
            ['value' => (string) ((int) $data['pack'])],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'catalogue_pack_amount'],
            ['value' => (string) ((int) $data['amount'] * 100)],
        );

        return response()->json([
            'message' => 'Catalogue price updated.',
            'data' => $this->catalogueOffer(),
        ]);
    }

    public function resetPassword(
        ResetShopPasswordRequest $request,
        User $user,
    ): JsonResponse {
        abort_unless($user->role === UserRole::Tailor, 404);

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset. Tell the shop owner the new password securely.',
            'data' => $this->shopPayload($user->fresh()->load('business')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array{free: int, pack: int, amount: int, amount_label: string}
     */
    private function catalogueOffer(): array
    {
        $billing = app(PaystackBilling::class);
        $pack = $billing->cataloguePack();

        return [
            'free' => $billing->sharedCatalogueFree(),
            'pack' => $pack['slots'],
            'amount' => (int) ($pack['amount'] / 100),
            'amount_label' => $pack['amount_label'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shopPayload(User $user, ?int $used = null): array
    {
        $payments = $user->relationLoaded('subscriptionPayments')
            ? $user->subscriptionPayments
            : $user->subscriptionPayments()->orderByDesc('created_at')->limit(5)->get();

        $latest = $payments->first();
        $extra = (int) ($user->business?->catalogue_extra ?? 0);
        $free = $user->business instanceof Business
            ? app(PaystackBilling::class)->catalogueFree($user->business)
            : (int) config('services.paystack.catalogue_free');
        $packKobo = $user->business?->catalogue_pack_amount;

        if ($used === null && $user->business !== null) {
            $used = CatalogueItem::query()->where('business_id', $user->business->id)->count();
        }

        return [
            'id' => $user->id,
            'owner_name' => $user->name,
            'email' => $user->email,
            'shop_name' => $user->business?->name,
            'phone' => $user->business?->phone,
            'country' => $user->business?->country,
            'state' => $user->business?->state,
            'created_at' => $user->created_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'suspended' => $user->isSuspended(),
            'suspended_at' => $user->suspended_at?->toIso8601String(),
            'subscription_status' => $user->subscription_status?->value ?? 'free',
            'subscription_access' => $user->subscriptionAccessStatus(),
            'subscribed_until' => $user->subscribed_until?->toDateString(),
            'catalogue_free' => $free,
            'catalogue_extra' => $extra,
            'catalogue_pack_amount' => $packKobo === null ? null : (int) round(((int) $packKobo) / 100),
            'catalogue_used' => $used ?? 0,
            'catalogue_limit' => $free + $extra,
            'latest_payment_status' => $latest?->status,
            'latest_payment_at' => $latest?->created_at?->toIso8601String(),
            'payments' => $payments
                ->map(fn (SubscriptionPayment $payment) => PaymentController::paymentPayload($payment, false))
                ->values()
                ->all(),
        ];
    }
}
