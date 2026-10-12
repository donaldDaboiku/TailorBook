<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_presence_counts_active_subscribers_by_state_and_hides_private_shops(): void
    {
        config([
            'services.support.email' => 'hello@tailormate.test',
            'services.support.phone' => '08031234567',
            'services.support.whatsapp' => '08031234567',
            'services.support.instagram' => 'https://instagram.com/tailormate',
            'services.support.facebook' => 'tailormate',
            'services.paystack.plan_amount' => 500000,
            'services.paystack.plan_days' => 30,
            'services.paystack.plan_label' => 'Monthly shop plan',
        ]);

        $this->subscriber('Lagos');
        $this->subscriber('Lagos');
        $this->subscriber('Rivers');

        $expired = User::factory()->create([
            'subscription_status' => SubscriptionStatus::Subscribed,
            'subscribed_until' => '2020-01-01',
        ]);
        Business::factory()->create([
            'user_id' => $expired->id,
            'state' => 'Kano',
            'name' => 'Hidden Expired Shop',
        ]);

        $free = User::factory()->create();
        Business::factory()->create([
            'user_id' => $free->id,
            'state' => 'Oyo',
            'name' => 'Hidden Free Shop',
        ]);

        $this->getJson('/api/public/presence')
            ->assertOk()
            ->assertJsonPath('data.subscribers', 4)
            ->assertJsonPath('data.plan.amount_label', '₦5,000.00')
            ->assertJsonPath('data.plan.days', 30)
            ->assertJsonPath('data.states.0.state', 'Lagos')
            ->assertJsonPath('data.states.0.subscribers', 2)
            ->assertJsonFragment(['state' => 'Rivers', 'subscribers' => 1])
            ->assertJsonFragment(['state' => 'Oyo', 'subscribers' => 1])
            ->assertJsonPath('data.contact.email', 'hello@tailormate.test')
            ->assertJsonPath('data.contact.whatsapp_url', 'https://wa.me/2348031234567?text=Hello%20TailorMate')
            ->assertJsonPath('data.social.0.name', 'Instagram')
            ->assertJsonPath('data.social.0.url', 'https://instagram.com/tailormate')
            ->assertJsonCount(1, 'data.social')
            ->assertJsonMissing(['name' => 'Hidden Expired Shop'])
            ->assertJsonMissing(['name' => 'Hidden Free Shop'])
            ->assertJsonMissing(['state' => 'Kano']);
    }

    public function test_register_requires_a_nigerian_state(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'business_name' => 'Ada Atelier',
            'phone' => '08031234567',
            'country' => 'NG',
            'state' => 'Paris',
            'measurement_unit' => 'in',
            'currency' => 'NGN',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['state']);
    }

    public function test_anyone_can_send_feedback_and_only_an_admin_can_read_it(): void
    {
        Mail::fake();
        config(['services.support.email' => 'admin@tailormate.test']);

        $this->postJson('/api/public/feedback', [
            'name' => 'Bola',
            'contact' => 'bola@example.com',
            'message' => 'The jobs list is clear.',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'The TailorMate admin received your message.');

        Mail::assertSentCount(1);

        $this->getJson('/api/admin/feedback')->assertUnauthorized();

        $tailor = User::factory()->create();
        Business::factory()->create(['user_id' => $tailor->id]);
        Sanctum::actingAs($tailor);
        $this->getJson('/api/admin/feedback')->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/feedback')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Bola')
            ->assertJsonPath('data.0.contact', 'bola@example.com')
            ->assertJsonPath('data.0.message', 'The jobs list is clear.');
    }

    private function subscriber(string $state): void
    {
        $user = User::factory()->create([
            'subscription_status' => SubscriptionStatus::Subscribed,
            'subscribed_until' => now()->addDays(10)->toDateString(),
        ]);

        Business::factory()->create([
            'user_id' => $user->id,
            'state' => $state,
        ]);
    }
}
