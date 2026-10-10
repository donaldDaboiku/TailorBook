<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_receipt_uses_custom_text_and_can_be_emailed(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Ada Atelier',
            'phone' => '08031234567',
            'receipt_header' => 'Thank you for sewing with us',
            'receipt_footer' => 'No refund after collection',
        ]);
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'name' => 'Bola',
            'email' => 'bola@example.com',
        ]);
        $payment = Payment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'amount' => '15000.00',
            'method' => 'cash',
            'paid_on' => '2026-10-10',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/customers/{$customer->id}/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.email', 'bola@example.com');

        $text = $response->json('data.text');

        $this->assertStringContainsString('Ada Atelier', $text);
        $this->assertStringContainsString('Thank you for sewing with us', $text);
        $this->assertStringContainsString('No refund after collection', $text);
        $this->assertStringContainsString('Bola', $text);
        $this->assertStringContainsString('₦15,000.00', $text);

        $this->postJson("/api/customers/{$customer->id}/payments/{$payment->id}/receipt/email")
            ->assertOk()
            ->assertJsonPath('message', 'Receipt sent to bola@example.com.');

        Mail::assertSentCount(1);
    }
}
