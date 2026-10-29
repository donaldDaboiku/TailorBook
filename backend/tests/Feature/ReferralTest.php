<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Salesperson;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\PaystackBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_code_is_kept_until_the_invited_shop_pays(): void
    {
        $host = Business::factory()->create(['invite_code' => 'HOST01']);

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'guest@example.com',
            'business_name' => 'Guest Atelier',
            'invite_code' => 'host01',
        ]))->assertCreated();

        $guest = Business::query()->where('name', 'Guest Atelier')->first();
        $this->assertNotNull($guest);
        $this->assertSame($host->id, $guest->referred_by_business_id);
        $this->assertNull($guest->referred_by_salesperson_id);
        $this->assertNotSame('', (string) $guest->invite_code);

        Sanctum::actingAs($host->user);
        $this->getJson('/api/referral')
            ->assertOk()
            ->assertJsonPath('data.code', 'HOST01')
            ->assertJsonPath('data.signed_up', 1)
            ->assertJsonPath('data.paid', 0)
            ->assertJsonPath('data.commission_label', '₦0.00');

        $this->planPayment($guest->user, 'tm_guest_plan');

        $this->getJson('/api/referral')
            ->assertOk()
            ->assertJsonPath('data.paid', 1);

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'bad@example.com',
            'invite_code' => 'NOPE',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['invite_code']);

        $this->assertDatabaseMissing('users', ['email' => 'bad@example.com']);
    }

    public function test_a_salesperson_code_is_separate_and_commission_starts_after_the_free_count(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/admin/salespeople', ['name' => 'Tunde'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tunde');
        $code = $created->json('data.code');
        $this->assertNotEmpty($code);

        $this->putJson('/api/admin/referrals', [
            'skip' => 1,
            'amount' => 1000,
        ])->assertOk()
            ->assertJsonPath('data.skip', 1)
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.amount_label', '₦1,000.00');

        $first = $this->registerUnder($code, 'one@example.com', 'One Atelier');
        $second = $this->registerUnder($code, 'two@example.com', 'Two Atelier');

        $this->assertSame(
            Salesperson::query()->where('code', $code)->value('id'),
            $first->referred_by_salesperson_id,
        );
        $this->assertNull($first->referred_by_business_id);

        $this->planPayment($first->user, 'tm_one');
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.salespeople.0.paid', 1)
            ->assertJsonPath('data.salespeople.0.commission_label', '₦0.00');

        $this->planPayment($second->user, 'tm_two');
        $this->planPayment($second->user, 'tm_two_renew', 'catalogue');
        $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.salespeople.0.signed_up', 2)
            ->assertJsonPath('data.salespeople.0.paid', 2)
            ->assertJsonPath('data.salespeople.0.commission_label', '₦1,000.00')
            ->assertJsonPath('data.salespeople.0.shops.0.paid', true);

        SubscriptionPayment::query()->where('reference', 'tm_two')->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);

        $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.salespeople.0.paid', 1)
            ->assertJsonPath('data.salespeople.0.commission_label', '₦0.00');
    }

    public function test_a_paying_invite_gives_the_host_shop_a_free_month_after_admin_approval(): void
    {
        $host = Business::factory()->create(['name' => 'Host Atelier', 'invite_code' => 'HOST02']);
        $guest = $this->registerUnder('HOST02', 'guest-month@example.com', 'Guest Atelier');

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.rewards.0.status', 'signed_up')
            ->assertJsonPath('data.activities.0.kind', 'signed_up');

        $billing = app(PaystackBilling::class);
        $markPaid = new \ReflectionMethod(PaystackBilling::class, 'markPaid');
        $payment = SubscriptionPayment::query()->create([
            'user_id' => $guest->user_id,
            'reference' => 'tm_guest_month',
            'amount' => 500000,
            'currency' => 'NGN',
            'purpose' => 'plan',
            'status' => 'pending',
        ]);
        $markPaid->invoke($billing, $payment, []);

        $rewardId = $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.rewards.0.status', 'pending')
            ->json('data.rewards.0.id');

        $billing->markRefunded('tm_guest_month');
        $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.rewards.0.status', 'signed_up');

        $again = SubscriptionPayment::query()->create([
            'user_id' => $guest->user_id,
            'reference' => 'tm_guest_month_2',
            'amount' => 500000,
            'currency' => 'NGN',
            'purpose' => 'plan',
            'status' => 'pending',
        ]);
        $markPaid->invoke($billing, $again, []);

        $this->postJson("/api/admin/referrals/{$rewardId}/approve")->assertOk();

        $hostUser = $host->user()->first();
        $this->assertSame('subscribed', $hostUser?->subscription_status?->value);
        $this->assertSame(now()->addDays(30)->toDateString(), $hostUser?->subscribed_until?->toDateString());

        $board = $this->getJson('/api/admin/referrals')->assertOk()->json('data');
        $this->assertSame('approved', $board['rewards'][0]['status']);
        $this->assertSame(now()->addDays(30)->toDateString(), $board['rewards'][0]['granted_until']);
        $this->assertTrue(collect($board['activities'])->contains(
            fn (array $row) => $row['kind'] === 'approved' && str_contains($row['summary'], 'Host Atelier'),
        ));

        $this->postJson("/api/admin/referrals/{$rewardId}/approve")->assertUnprocessable();

        Sanctum::actingAs($host->user);
        $this->getJson('/api/referral')
            ->assertOk()
            ->assertJsonPath('data.months_pending', 0)
            ->assertJsonPath('data.months_approved', 1);
    }

    public function test_a_shop_cannot_change_the_referral_rate(): void
    {
        $tailor = User::factory()->create();
        Business::factory()->create(['user_id' => $tailor->id]);
        Sanctum::actingAs($tailor);

        $this->putJson('/api/admin/referrals', [
            'skip' => 0,
            'amount' => 500,
        ])->assertForbidden();
    }

    private function registerUnder(string $code, string $email, string $shop): Business
    {
        $this->postJson('/api/auth/register', $this->payload([
            'email' => $email,
            'business_name' => $shop,
            'invite_code' => $code,
        ]))->assertCreated();

        $business = Business::query()->where('name', $shop)->first();
        $this->assertInstanceOf(Business::class, $business);

        return $business;
    }

    private function planPayment(User $user, string $reference, string $purpose = 'plan'): void
    {
        SubscriptionPayment::query()->create([
            'user_id' => $user->id,
            'reference' => $reference,
            'amount' => 500000,
            'currency' => 'NGN',
            'purpose' => $purpose,
            'status' => 'success',
            'paid_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'business_name' => 'Ada Atelier',
            'phone' => '08031234567',
            'country' => 'NG',
            'state' => 'Lagos',
            'measurement_unit' => 'in',
            'currency' => 'NGN',
        ], $overrides);
    }
}
