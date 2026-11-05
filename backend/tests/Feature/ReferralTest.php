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

    public function test_five_paying_invites_earn_one_free_month_after_admin_approval(): void
    {
        $host = Business::factory()->create(['name' => 'Host Atelier', 'invite_code' => 'HOST02']);
        $guests = [];

        for ($number = 1; $number <= 5; $number++) {
            $guests[] = $this->registerUnder('HOST02', "guest{$number}@example.com", "Guest {$number}");
        }

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.rewards', [])
            ->assertJsonPath('data.referrals.0.status', 'signed_up')
            ->assertJsonPath('data.overview.registered', 5)
            ->assertJsonPath('data.offer.threshold', 5);

        $billing = app(PaystackBilling::class);
        $markPaid = new \ReflectionMethod(PaystackBilling::class, 'markPaid');

        foreach (array_slice($guests, 0, 4) as $index => $guest) {
            $payment = SubscriptionPayment::query()->create([
                'user_id' => $guest->user_id,
                'reference' => 'tm_guest_'.$index,
                'amount' => 500000,
                'currency' => 'NGN',
                'purpose' => 'plan',
                'status' => 'pending',
            ]);
            $markPaid->invoke($billing, $payment, []);
        }

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.rewards', [])
            ->assertJsonPath('data.overview.successful', 4);

        $fifth = SubscriptionPayment::query()->create([
            'user_id' => $guests[4]->user_id,
            'reference' => 'tm_guest_4',
            'amount' => 500000,
            'currency' => 'NGN',
            'purpose' => 'plan',
            'status' => 'pending',
        ]);
        $markPaid->invoke($billing, $fifth, []);

        $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.rewards.0.status', 'pending')
            ->assertJsonPath('data.rewards.0.milestone', 1);

        $billing->markRefunded('tm_guest_0');
        $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.rewards', [])
            ->assertJsonPath('data.overview.successful', 4);

        $sixth = $this->registerUnder('HOST02', 'guest6@example.com', 'Guest 6');
        $again = SubscriptionPayment::query()->create([
            'user_id' => $sixth->user_id,
            'reference' => 'tm_guest_6',
            'amount' => 500000,
            'currency' => 'NGN',
            'purpose' => 'plan',
            'status' => 'pending',
        ]);
        $markPaid->invoke($billing, $again, []);

        $rewardId = $this->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.rewards.0.status', 'pending')
            ->json('data.rewards.0.id');

        $this->postJson("/api/admin/referrals/{$rewardId}/approve")->assertOk();

        $hostUser = $host->user()->first();
        $this->assertSame('subscribed', $hostUser?->subscription_status?->value);
        $this->assertSame(now()->addMonth()->toDateString(), $hostUser?->subscribed_until?->toDateString());

        $board = $this->getJson('/api/admin/referrals')->assertOk()->json('data');
        $this->assertSame('approved', $board['rewards'][0]['status']);
        $this->assertSame(now()->addMonth()->toDateString(), $board['rewards'][0]['granted_until']);
        $this->assertTrue(collect($board['activities'])->contains(
            fn (array $row) => $row['kind'] === 'approved' && str_contains($row['summary'], 'Host Atelier'),
        ));

        $this->postJson("/api/admin/referrals/{$rewardId}/approve")->assertUnprocessable();

        Sanctum::actingAs($host->user);
        $this->getJson('/api/referral')
            ->assertOk()
            ->assertJsonPath('data.threshold', 5)
            ->assertJsonPath('data.successful', 5)
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.months_pending', 0)
            ->assertJsonPath('data.months_approved', 1)
            ->assertJsonFragment(['shop_name' => 'Guest 6', 'status_label' => 'Successful'])
            ->assertJsonMissingPath('data.history.0.email');
    }

    public function test_a_shop_cannot_use_its_own_code_and_an_inactive_salesperson_code_is_refused(): void
    {
        $host = Business::factory()->create(['invite_code' => 'MINE01']);

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'same-owner@example.com',
            'business_name' => 'Second Shop',
            'invite_code' => 'MINE01',
        ]))->assertCreated();

        $second = Business::query()->where('name', 'Second Shop')->first();
        $this->assertNotSame($host->id, $second?->id);
        $this->assertSame($host->id, $second?->referred_by_business_id);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/admin/salespeople', ['name' => 'Off Duty'])->assertCreated();
        $this->putJson('/api/admin/salespeople/'.$created->json('data.id'), ['active' => false])->assertOk();

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'off@example.com',
            'invite_code' => $created->json('data.code'),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['invite_code']);
        $this->assertDatabaseMissing('users', ['email' => 'off@example.com']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\Referrals::class)->assign($host, ['business_id' => $host->id]);
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
            'adult' => true,
            'privacy' => true,
            'terms' => true,
        ], $overrides);
    }
}
