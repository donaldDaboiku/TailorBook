<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' => 'sk_test_dummy',
            'services.paystack.plan_amount' => 500000,
            'services.paystack.plan_days' => 30,
            'services.paystack.plan_label' => 'Monthly shop plan',
            'services.paystack.callback_url' => 'http://localhost:5173/?billing=1',
        ]);
    }

    public function test_tailor_can_start_checkout_and_verify_payment(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'subscription_status' => SubscriptionStatus::Free,
        ]);
        Business::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'access',
                    'reference' => 'ignored',
                ],
            ]),
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'amount' => 500000,
                    'currency' => 'NGN',
                    'reference' => 'will-replace',
                ],
            ]),
        ]);

        Sanctum::actingAs($user);

        $checkout = $this->postJson('/api/billing/checkout')
            ->assertOk()
            ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/test');

        $reference = $checkout->json('data.reference');
        $this->assertNotEmpty($reference);
        $this->assertDatabaseHas('subscription_payments', [
            'reference' => $reference,
            'user_id' => $user->id,
            'status' => 'pending',
            'amount' => 500000,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'amount' => 500000,
                    'currency' => 'NGN',
                    'reference' => $reference,
                ],
            ]),
        ]);

        $this->postJson('/api/billing/verify', ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.subscription_status', 'subscribed')
            ->assertJsonPath('data.subscription_access', 'subscribed');

        $fresh = $user->fresh();
        $this->assertSame(SubscriptionStatus::Subscribed, $fresh->subscription_status);
        $this->assertNotNull($fresh->subscribed_until);
        $this->assertTrue($fresh->subscribed_until->greaterThanOrEqualTo(now()->toDateString()));

        $this->assertDatabaseHas('subscription_payments', [
            'reference' => $reference,
            'status' => 'success',
        ]);
    }

    public function test_expired_shop_can_login_and_only_use_billing_routes(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password',
            'subscription_status' => SubscriptionStatus::Subscribed,
            'subscribed_until' => '2020-01-01',
        ]);
        Business::factory()->create(['user_id' => $user->id]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame('expired', $login->json('user.subscription_access'));

        Sanctum::actingAs($user);

        $this->getJson('/api/customers')->assertForbidden();
        $this->getJson('/api/billing/plan')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.amount', 500000);
    }

    public function test_webhook_activates_subscription_with_valid_signature(): void
    {
        $user = User::factory()->create([
            'subscription_status' => SubscriptionStatus::Free,
        ]);
        Business::factory()->create(['user_id' => $user->id]);

        $payment = SubscriptionPayment::query()->create([
            'user_id' => $user->id,
            'reference' => 'tm_webhook_ref',
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
            'data' => [
                'reference' => $payment->reference,
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha512', $body, 'sk_test_dummy');

        $this->call(
            'POST',
            '/api/billing/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $body,
        )->assertOk();

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame(SubscriptionStatus::Subscribed, $user->fresh()->subscription_status);
    }
}
