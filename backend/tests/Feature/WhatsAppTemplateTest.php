<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_are_filled_and_link_to_whatsapp(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create([
            'business_id' => $user->business->id,
            'name' => 'Grace',
            'phone' => '08031234567',
            'whatsapp_phone' => '08031234567',
        ]);

        CustomerJob::factory()->create([
            'business_id' => $user->business->id,
            'customer_id' => $customer->id,
            'title' => 'Suit',
            'agreed_amount' => '80000.00',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/customers/{$customer->id}/whatsapp-templates")
            ->assertOk()
            ->assertJsonPath('data.phone', '08031234567')
            ->assertJsonPath('data.whatsapp_number', '2348031234567')
            ->assertJsonCount(3, 'data.templates')
            ->assertJsonPath('data.templates.0.key', 'hello')
            ->assertJsonPath('data.templates.1.key', 'balance_reminder')
            ->assertJsonPath('data.templates.2.key', 'outfit_ready')
            ->assertJsonPath(
                'data.templates.1.body',
                'Good day Grace, this is Ada Atelier. Your outstanding balance is ₦80,000.00. Please pay when you can. Thank you.',
            )
            ->assertJsonPath(
                'data.templates.2.body',
                'Good day Grace, this is Ada Atelier. Your Suit is ready for pickup. Thank you.',
            )
            ->assertJsonPath(
                'data.templates.0.url',
                'https://wa.me/2348031234567?text='.rawurlencode(
                    'Good day Grace, this is Ada Atelier. How can we help you today?',
                ),
            );
    }

    public function test_other_shops_cannot_load_templates(): void
    {
        $owner = $this->tailor();
        $other = $this->tailor('other@example.com');
        $customer = Customer::factory()->create([
            'business_id' => $owner->business->id,
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/customers/{$customer->id}/whatsapp-templates")
            ->assertForbidden();
    }

    private function tailor(string $email = 'ada@example.com'): User
    {
        $user = User::factory()->create([
            'name' => 'Ada',
            'email' => $email,
        ]);

        Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Ada Atelier',
            'phone' => '08030000000',
            'currency' => 'NGN',
        ]);

        return $user->fresh(['business']);
    }
}
