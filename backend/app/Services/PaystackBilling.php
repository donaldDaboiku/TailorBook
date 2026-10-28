<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\CatalogueItem;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaystackBilling
{
    public function plan(): array
    {
        $amount = (int) config('services.paystack.plan_amount');
        $days = (int) config('services.paystack.plan_days');
        $naira = number_format($amount / 100, 2, '.', ',');

        return [
            'label' => (string) config('services.paystack.plan_label'),
            'amount' => $amount,
            'amount_label' => "₦{$naira}",
            'currency' => 'NGN',
            'days' => $days,
            'enabled' => $this->configured(),
        ];
    }

    public function configured(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    /**
     * @return array{authorization_url: string, reference: string}
     */
    public function initialize(User $user): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Paystack is not configured.');
        }

        if ($user->isAdmin()) {
            throw new RuntimeException('Admin accounts do not need a shop plan.');
        }

        $plan = $this->plan();
        $reference = 'tm_'.Str::lower(Str::ulid());

        SubscriptionPayment::query()->create([
            'user_id' => $user->id,
            'reference' => $reference,
            'amount' => $plan['amount'],
            'currency' => 'NGN',
            'purpose' => 'plan',
            'status' => 'pending',
        ]);

        $response = Http::withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $plan['amount'],
                'currency' => 'NGN',
                'reference' => $reference,
                'callback_url' => (string) config('services.paystack.callback_url'),
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_days' => $plan['days'],
                    'custom_fields' => [
                        [
                            'display_name' => 'Shop plan',
                            'variable_name' => 'shop_plan',
                            'value' => $plan['label'],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            SubscriptionPayment::query()->where('reference', $reference)->delete();

            throw new RuntimeException(
                $response->json('message') ?? 'Could not start Paystack checkout.',
            );
        }

        return [
            'authorization_url' => (string) $response->json('data.authorization_url'),
            'reference' => $reference,
        ];
    }

    /**
     * @return array{authorization_url: string, reference: string}
     */
    public function initializeCatalogue(User $user): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Paystack is not configured.');
        }

        if ($user->isAdmin()) {
            throw new RuntimeException('Admin accounts do not buy catalogue space.');
        }

        $business = $user->business;

        if (! $business instanceof Business) {
            throw new RuntimeException('Set up your shop first.');
        }

        $limit = $this->catalogueLimit($business);
        $used = CatalogueItem::query()->where('business_id', $business->id)->count();

        if ($used < $limit) {
            throw new RuntimeException('This shop can still add styles before paying.');
        }

        $pack = $this->cataloguePack($business);
        $reference = 'tc_'.Str::lower(Str::ulid());
        $callback = (string) config('services.paystack.callback_url');
        $callback .= (str_contains($callback, '?') ? '&' : '?').'kind=catalogue';

        SubscriptionPayment::query()->create([
            'user_id' => $user->id,
            'reference' => $reference,
            'amount' => $pack['amount'],
            'currency' => 'NGN',
            'purpose' => 'catalogue',
            'status' => 'pending',
        ]);

        $response = Http::withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $pack['amount'],
                'currency' => 'NGN',
                'reference' => $reference,
                'callback_url' => $callback,
                'metadata' => [
                    'user_id' => $user->id,
                    'purpose' => 'catalogue',
                    'slots' => $pack['slots'],
                ],
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            SubscriptionPayment::query()->where('reference', $reference)->delete();

            throw new RuntimeException(
                $response->json('message') ?? 'Could not start Paystack checkout.',
            );
        }

        return [
            'authorization_url' => (string) $response->json('data.authorization_url'),
            'reference' => $reference,
        ];
    }

    /**
     * @return array{slots: int, amount: int, amount_label: string}
     */
    public function cataloguePack(?Business $business = null): array
    {
        $amount = $this->storedInt(
            'catalogue_pack_amount',
            (int) config('services.paystack.catalogue_pack_amount'),
        );
        $naira = number_format($amount / 100, 2, '.', ',');

        return [
            'slots' => $this->storedInt(
                'catalogue_pack_slots',
                (int) config('services.paystack.catalogue_pack'),
            ),
            'amount' => $amount,
            'amount_label' => "₦{$naira}",
        ];
    }

    public function catalogueFree(Business $business): int
    {
        return $this->sharedCatalogueFree();
    }

    public function sharedCatalogueFree(): int
    {
        return $this->storedInt(
            'catalogue_free',
            (int) config('services.paystack.catalogue_free'),
        );
    }

    private function storedInt(string $key, int $fallback): int
    {
        $stored = Setting::valueFor($key);

        return is_numeric($stored) ? (int) $stored : $fallback;
    }

    public function catalogueLimit(Business $business): int
    {
        return $this->catalogueFree($business) + (int) $business->catalogue_extra;
    }

    public function verifyAndActivate(string $reference, ?User $expectedUser = null): User
    {
        if (! $this->configured()) {
            throw new RuntimeException('Paystack is not configured.');
        }

        $payment = SubscriptionPayment::query()->where('reference', $reference)->first();

        if ($payment === null) {
            throw new RuntimeException('Unknown payment reference.');
        }

        if ($expectedUser !== null && (int) $payment->user_id !== (int) $expectedUser->id) {
            throw new RuntimeException('This payment belongs to another account.');
        }

        if ($payment->status === 'success') {
            return User::query()->findOrFail($payment->user_id)->load('business');
        }

        if ($payment->status === 'refunded') {
            throw new RuntimeException('This payment was refunded.');
        }

        $response = Http::withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->get('https://api.paystack.co/transaction/verify/'.rawurlencode($reference));

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException(
                $response->json('message') ?? 'Could not verify payment.',
            );
        }

        $data = is_array($response->json('data')) ? $response->json('data') : [];
        $status = $data['status'] ?? null;
        $amount = $this->koboAmount($data['amount'] ?? null);
        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $expectedAmount = (int) $payment->amount;
        $paidReference = $data['reference'] ?? null;

        $payment->forceFill([
            'channel' => is_string($data['channel'] ?? null) ? $data['channel'] : $payment->channel,
            'gateway_response' => is_string($data['gateway_response'] ?? null)
                ? $data['gateway_response']
                : $payment->gateway_response,
        ])->save();

        if (
            $status !== 'success'
            || $currency !== 'NGN'
            || $amount !== $expectedAmount
            || ! is_string($paidReference)
            || $paidReference !== $reference
        ) {
            $this->markFailed(
                $payment,
                is_string($data['gateway_response'] ?? null)
                    ? $data['gateway_response']
                    : 'Payment was not successful.',
            );

            throw new RuntimeException('Payment was not successful.');
        }

        return $this->markPaid($payment, $data);
    }

    public function handleWebhook(string $rawBody, ?string $signature): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Paystack is not configured.');
        }

        $secret = (string) config('services.paystack.secret_key');
        $expected = hash_hmac('sha512', $rawBody, $secret);

        if (! is_string($signature) || strlen($signature) !== strlen($expected) || ! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid Paystack signature.');
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid webhook payload.');
        }

        $event = $payload['event'] ?? null;
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if ($event === 'charge.success') {
            $reference = $this->referenceFromPayload($data);
            if ($reference !== null) {
                $this->verifyAndActivate($reference);
            }

            return;
        }

        if ($event === 'charge.failed') {
            $reference = $this->referenceFromPayload($data);
            if ($reference === null) {
                return;
            }

            $payment = SubscriptionPayment::query()->where('reference', $reference)->first();
            if ($payment === null || $payment->status === 'success' || $payment->status === 'refunded') {
                return;
            }

            $this->markFailed(
                $payment,
                is_string($data['gateway_response'] ?? null)
                    ? $data['gateway_response']
                    : (is_string($data['message'] ?? null) ? $data['message'] : 'Payment failed.'),
                is_string($data['channel'] ?? null) ? $data['channel'] : null,
            );

            return;
        }

        if (in_array($event, ['refund.processed', 'charge.refunded'], true)) {
            $reference = $this->referenceFromRefundPayload($data);
            if ($reference !== null) {
                $this->revokeIfPaystackConfirmsRefund($reference);
            }
        }
    }

    public function markFailed(
        SubscriptionPayment $payment,
        string $message,
        ?string $channel = null,
    ): void {
        if (in_array($payment->status, ['success', 'refunded'], true)) {
            return;
        }

        $payment->forceFill([
            'status' => 'failed',
            'failure_message' => Str::limit($message, 240),
            'channel' => $channel ?? $payment->channel,
            'gateway_response' => Str::limit($message, 240),
        ])->save();
    }

    public function markRefunded(string $reference): void
    {
        $payment = SubscriptionPayment::query()->where('reference', $reference)->first();

        if ($payment === null || $payment->status === 'refunded') {
            return;
        }

        DB::transaction(function () use ($payment) {
            /** @var SubscriptionPayment $locked */
            $locked = SubscriptionPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'refunded') {
                return;
            }

            $wasSuccess = $locked->status === 'success';

            $locked->forceFill([
                'status' => 'refunded',
                'refunded_at' => now(),
                'failure_message' => $wasSuccess ? null : $locked->failure_message,
            ])->save();

            if (! $wasSuccess) {
                return;
            }

            /** @var User $user */
            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();

            if ($locked->purpose === 'catalogue') {
                $shop = Business::query()->where('user_id', $user->id)->lockForUpdate()->first();

                if ($shop instanceof Business) {
                    $pack = $this->cataloguePack()['slots'];
                    $shop->forceFill([
                        'catalogue_extra' => max(0, (int) $shop->catalogue_extra - $pack),
                    ])->save();
                }

                return;
            }
            $days = (int) config('services.paystack.plan_days');

            if ($user->subscribed_until === null) {
                $user->forceFill([
                    'subscription_status' => SubscriptionStatus::Free,
                    'subscribed_until' => null,
                ])->save();
                $user->tokens()->delete();

                return;
            }

            $nextUntil = $user->subscribed_until->copy()->subDays($days);

            if ($nextUntil->copy()->endOfDay()->isPast()) {
                $user->forceFill([
                    'subscription_status' => SubscriptionStatus::Free,
                    'subscribed_until' => null,
                ])->save();
                $user->tokens()->delete();

                return;
            }

            $user->forceFill([
                'subscription_status' => SubscriptionStatus::Subscribed,
                'subscribed_until' => $nextUntil->toDateString(),
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function markPaid(SubscriptionPayment $payment, array $data = []): User
    {
        $notice = null;

        $user = DB::transaction(function () use ($payment, $data, &$notice) {
            /** @var SubscriptionPayment $locked */
            $locked = SubscriptionPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'success') {
                return User::query()->findOrFail($locked->user_id)->load('business');
            }

            if ($locked->status === 'refunded') {
                throw new RuntimeException('This payment was refunded.');
            }

            /** @var User $user */
            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();

            if ($locked->purpose === 'catalogue') {
                $shop = Business::query()->where('user_id', $user->id)->lockForUpdate()->first();

                if (! $shop instanceof Business) {
                    throw new RuntimeException('Set up your shop first.');
                }

                $shop->forceFill([
                    'catalogue_extra' => (int) $shop->catalogue_extra + $this->cataloguePack()['slots'],
                ])->save();
            } else {
                $days = (int) config('services.paystack.plan_days');
                $extending = $user->subscription_status === SubscriptionStatus::Subscribed
                    && $user->subscribed_until !== null
                    && $user->subscribed_until->copy()->endOfDay()->isFuture();
                $notice = $extending ? 'renewed' : 'welcome';
                $base = $extending
                    ? $user->subscribed_until->copy()
                    : now();

                $user->forceFill([
                    'subscription_status' => SubscriptionStatus::Subscribed,
                    'subscribed_until' => $base->addDays($days)->toDateString(),
                ])->save();
            }

            $locked->forceFill([
                'status' => 'success',
                'paid_at' => now(),
                'failure_message' => null,
                'channel' => is_string($data['channel'] ?? null) ? $data['channel'] : $locked->channel,
                'gateway_response' => is_string($data['gateway_response'] ?? null)
                    ? $data['gateway_response']
                    : $locked->gateway_response,
            ])->save();

            return $user->fresh()->load('business');
        });

        if ($notice === 'welcome') {
            app(ShopNotices::class)->welcomeSubscriber($user);
        } elseif ($notice === 'renewed') {
            app(ShopNotices::class)->renewalConfirmed($user);
        }

        return $user;
    }

    private function revokeIfPaystackConfirmsRefund(string $reference): void
    {
        $payment = SubscriptionPayment::query()->where('reference', $reference)->first();

        if ($payment === null || $payment->status === 'refunded') {
            return;
        }

        $response = Http::withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->get('https://api.paystack.co/transaction/verify/'.rawurlencode($reference));

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Could not verify refund.');
        }

        $data = is_array($response->json('data')) ? $response->json('data') : [];
        $paidReference = $data['reference'] ?? null;

        if (! is_string($paidReference) || $paidReference !== $reference) {
            throw new RuntimeException('Paystack reference did not match.');
        }

        if (! $this->paystackConfirmsRefund($data, (int) $payment->amount)) {
            return;
        }

        $this->markRefunded($reference);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paystackConfirmsRefund(array $data, int $expectedAmount): bool
    {
        if (($data['status'] ?? null) === 'reversed') {
            return true;
        }

        $refunds = $data['refunds'] ?? null;

        if (! is_array($refunds)) {
            return false;
        }

        foreach ($refunds as $refund) {
            if (! is_array($refund) || ! in_array($refund['status'] ?? null, ['processed', 'success'], true)) {
                continue;
            }

            if ($this->koboAmount($refund['amount'] ?? null) === $expectedAmount) {
                return true;
            }
        }

        return false;
    }

    private function koboAmount(mixed $amount): ?int
    {
        if (is_int($amount)) {
            return $amount;
        }

        if (is_string($amount) && preg_match('/^[0-9]+$/', $amount) === 1) {
            return (int) $amount;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function referenceFromPayload(array $data): ?string
    {
        $reference = $data['reference'] ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function referenceFromRefundPayload(array $data): ?string
    {
        foreach ([
            $data['transaction_reference'] ?? null,
            $data['reference'] ?? null,
            is_array($data['transaction'] ?? null) ? ($data['transaction']['reference'] ?? null) : null,
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
