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
            'address' => '12 Allen Avenue, Ikeja',
            'receipt_header' => 'Thank you for sewing with us',
            'receipt_footer' => 'No refund after collection',
        ]);
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'name' => 'Bola',
            'phone' => '08031234567',
            'whatsapp_phone' => '08031234567',
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

        $receipt = $this->getJson("/api/customers/{$customer->id}/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.email', 'bola@example.com')
            ->assertJsonPath('data.shop_name', 'Ada Atelier')
            ->assertJsonPath('data.shop_address', '12 Allen Avenue, Ikeja')
            ->assertJsonPath('data.shop_email', $user->email)
            ->assertJsonPath('data.header', 'Thank you for sewing with us')
            ->assertJsonPath('data.footer', 'No refund after collection')
            ->assertJsonPath('data.customer', 'Bola')
            ->assertJsonPath('data.amount_label', '₦15,000.00')
            ->assertJsonPath('data.receipt_number', 'RCP-00001');

        $url = (string) $receipt->json('data.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/2348031234567?text=', $url);
        $this->assertStringContainsString(rawurlencode('Hello Bola, this is Ada Atelier.'), $url);

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->postJson("/api/customers/{$customer->id}/payments/{$payment->id}/receipt/email", [
            'image' => $png,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Receipt sent to bola@example.com.');
    }
}
