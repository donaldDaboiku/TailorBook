<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
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

        $data = $response->json('data') ?? [];
        $status = $data['status'] ?? null;
        $amount = (int) ($data['amount'] ?? 0);
        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $expectedAmount = (int) $payment->amount;

        $payment->forceFill([
            'channel' => is_string($data['channel'] ?? null) ? $data['channel'] : $payment->channel,
            'gateway_response' => is_string($data['gateway_response'] ?? null)
                ? $data['gateway_response']
                : $payment->gateway_response,
        ])->save();

        if ($status !== 'success' || $currency !== 'NGN' || $amount !== $expectedAmount) {
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

        if (! is_string($signature) || ! hash_equals($expected, $signature)) {
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
                $this->markRefunded($reference);
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
        return DB::transaction(function () use ($payment, $data) {
            /** @var SubscriptionPayment $locked */
            $locked = SubscriptionPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'success') {
                return User::query()->findOrFail($locked->user_id)->load('business');
            }

            /** @var User $user */
            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $days = (int) config('services.paystack.plan_days');
            $base = $user->subscribed_until !== null && $user->subscribed_until->copy()->endOfDay()->isFuture()
                ? $user->subscribed_until->copy()
                : now();

            $user->forceFill([
                'subscription_status' => SubscriptionStatus::Subscribed,
                'subscribed_until' => $base->addDays($days)->toDateString(),
            ])->save();

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
