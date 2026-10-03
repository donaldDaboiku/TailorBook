<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tailor_can_create_and_list_customers(): void
    {
        $user = $this->tailor();

        Sanctum::actingAs($user);

        $this->postJson('/api/customers', [
            'name' => 'Grace',
            'phone' => '08031234567',
            'gender' => 'female',
            'notes' => 'Prefers ankara',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Grace')
            ->assertJsonPath('data.phone', '08031234567')
            ->assertJsonPath('data.whatsapp_phone', '08031234567')
            ->assertJsonPath('data.whatsapp_url', 'https://wa.me/2348031234567');

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Grace');
    }

    public function test_customers_can_be_searched_by_name_or_phone(): void
    {
        $user = $this->tailor();

        Customer::factory()->create([
            'business_id' => $user->business->id,
            'name' => 'Grace Okonkwo',
            'phone' => '08031111111',
        ]);
        Customer::factory()->create([
            'business_id' => $user->business->id,
            'name' => 'Chinedu',
            'phone' => '08032222222',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/customers?q=Grace')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Grace Okonkwo');

        $this->getJson('/api/customers?q=0803222')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Chinedu');
    }

    public function test_a_tailor_cannot_see_another_shop_customer(): void
    {
        $owner = $this->tailor();
        $other = $this->tailor('other@example.com');

        $customer = Customer::factory()->create([
            'business_id' => $owner->business->id,
            'name' => 'Grace',
            'phone' => '08031234567',
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/customers/{$customer->id}")->assertForbidden();
        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_phone_numbers_must_be_unique_inside_one_shop(): void
    {
        $user = $this->tailor();

        Customer::factory()->create([
            'business_id' => $user->business->id,
            'phone' => '08031234567',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/customers', [
            'name' => 'Another Grace',
            'phone' => '08031234567',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'That phone number is already saved for a customer.');
    }

    public function test_archiving_a_customer_frees_the_phone_number(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create([
            'business_id' => $user->business->id,
            'name' => 'Grace',
            'phone' => '08031234567',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Customer archived.');

        $this->assertSoftDeleted($customer);

        $this->postJson('/api/customers', [
            'name' => 'Grace Okonkwo',
            'phone' => '08031234567',
        ])->assertCreated();
    }

    public function test_a_customer_can_be_updated(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create([
            'business_id' => $user->business->id,
            'name' => 'Grace',
            'phone' => '08031234567',
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/customers/{$customer->id}", [
            'name' => 'Grace Okonkwo',
            'phone' => '08037654321',
            'whatsapp_phone' => '08039998877',
            'address' => 'Enugu',
            'notes' => 'VIP',
            'gender' => 'female',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Grace Okonkwo')
            ->assertJsonPath('data.phone', '08037654321')
            ->assertJsonPath('data.whatsapp_phone', '08039998877')
            ->assertJsonPath('data.address', 'Enugu');
    }

    public function test_guests_cannot_manage_customers(): void
    {
        $this->getJson('/api/customers')->assertUnauthorized();
        $this->postJson('/api/customers', [
            'name' => 'Grace',
            'phone' => '08031234567',
        ])->assertUnauthorized();
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
            'country' => 'NG',
        ]);

        return $user->fresh(['business']);
    }
}
