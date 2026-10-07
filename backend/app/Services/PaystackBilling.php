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

        if ($status !== 'success' || $currency !== 'NGN' || $amount !== $expectedAmount) {
            $payment->update(['status' => 'failed']);

            throw new RuntimeException('Payment was not successful.');
        }

        return $this->markPaid($payment);
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

        if (($payload['event'] ?? null) !== 'charge.success') {
            return;
        }

        $reference = $payload['data']['reference'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return;
        }

        $this->verifyAndActivate($reference);
    }

    private function markPaid(SubscriptionPayment $payment): User
    {
        return DB::transaction(function () use ($payment) {
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
            ])->save();

            return $user->fresh()->load('business');
        });
    }
}
