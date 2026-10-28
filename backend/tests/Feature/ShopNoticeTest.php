<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Mail\ShopNoticeMail;
use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShopNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_and_plan_notices_are_sent_once(): void
    {
        Mail::fake();

        $this->shop('ending@example.com', SubscriptionStatus::Free, now()->addDays(3)->toDateString());
        $this->shop('ended@example.com', SubscriptionStatus::Free, now()->subDay()->toDateString());
        $this->shop('renew@example.com', SubscriptionStatus::Subscribed, now()->addDays(3)->toDateString());
        $this->shop('lapsed@example.com', SubscriptionStatus::Subscribed, now()->subDay()->toDateString());
        $this->shop('free@example.com', SubscriptionStatus::Free, null);

        $this->artisan('shops:notify')->assertSuccessful();
        $this->artisan('shops:notify')->assertSuccessful();

        Mail::assertSent(ShopNoticeMail::class, fn (ShopNoticeMail $mail) => $mail->hasTo('ending@example.com') && $mail->subjectLine === 'Your TailorMate trial ends soon');
        Mail::assertSent(ShopNoticeMail::class, fn (ShopNoticeMail $mail) => $mail->hasTo('ended@example.com') && $mail->subjectLine === 'Your TailorMate trial has ended');
        Mail::assertSent(ShopNoticeMail::class, fn (ShopNoticeMail $mail) => $mail->hasTo('renew@example.com') && $mail->subjectLine === 'Your TailorMate plan ends soon');
        Mail::assertSent(ShopNoticeMail::class, fn (ShopNoticeMail $mail) => $mail->hasTo('lapsed@example.com') && $mail->subjectLine === 'Your TailorMate plan has ended');
        Mail::assertNotSent(ShopNoticeMail::class, fn (ShopNoticeMail $mail) => $mail->hasTo('free@example.com'));
        Mail::assertSentCount(4);
    }

    public function test_a_renewal_payment_sends_a_renewal_email(): void
    {
        Mail::fake();
        config([
            'services.paystack.secret_key' => 'sk_test_dummy',
            'services.paystack.plan_days' => 30,
        ]);

        $user = $this->shop('renewed@example.com', SubscriptionStatus::Subscribed, now()->addDays(10)->toDateString());
        $payment = SubscriptionPayment::query()->create([
            'user_id' => $user->id,
            'reference' => 'tm_renew_ref',
            'amount' => 500000,
            'currency' => 'NGN',
            'status' => 'pending',
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 500000,
                    'currency' => 'NGN',
                    'reference' => $payment->reference,
                ],
            ]),
        ]);

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => $payment->reference],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/billing/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_dummy'),
            ],
            $body,
        )->assertOk();

        Mail::assertSent(ShopNoticeMail::class, function (ShopNoticeMail $mail) {
            return $mail->hasTo('renewed@example.com')
                && $mail->subjectLine === 'Your TailorMate plan was renewed'
                && str_contains($mail->bodyText, now()->addDays(40)->toDateString());
        });
    }

    private function shop(string $email, SubscriptionStatus $status, ?string $until): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'subscription_status' => $status,
            'subscribed_until' => $until,
        ]);
        Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Notice Atelier',
        ]);

        return $user;
    }
}
