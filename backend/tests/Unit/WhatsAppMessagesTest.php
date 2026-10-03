<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Payment;
use App\Models\User;
use App\Services\WhatsAppMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_include_name_shop_balance_and_garment(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Ada Atelier',
            'currency' => 'NGN',
        ]);
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'name' => 'Grace',
        ]);

        CustomerJob::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'title' => 'Gown',
            'agreed_amount' => '100000.00',
        ]);

        Payment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'amount' => '40000.00',
        ]);

        $templates = collect(WhatsAppMessages::forCustomer($customer->fresh(), $business))
            ->keyBy('key');

        $this->assertSame(
            'Good day Grace, this is Ada Atelier. How can we help you today?',
            $templates['hello']['body'],
        );
        $this->assertSame(
            'Good day Grace, this is Ada Atelier. Your outstanding balance is ₦60,000.00. Please pay when you can. Thank you.',
            $templates['balance_reminder']['body'],
        );
        $this->assertSame(
            'Good day Grace, this is Ada Atelier. Your Gown is ready for pickup. Thank you.',
            $templates['outfit_ready']['body'],
        );
    }
}
