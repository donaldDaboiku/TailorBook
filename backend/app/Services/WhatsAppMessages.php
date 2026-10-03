<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;

class WhatsAppMessages
{
    /**
     * @return list<array{key: string, label: string, body: string}>
     */
    public static function forCustomer(Customer $customer, Business $business): array
    {
        $name = $customer->name;
        $shop = $business->name;
        $balance = Money::format($customer->outstandingAmount(), $business->currency);
        $garment = $customer->jobs()->orderByDesc('service_date')->orderByDesc('created_at')->value('title');

        $outfitLine = is_string($garment) && $garment !== ''
            ? "Your {$garment} is ready for pickup."
            : 'Your outfit is ready for pickup.';

        return [
            [
                'key' => 'hello',
                'label' => 'Hello',
                'body' => "Good day {$name}, this is {$shop}. How can we help you today?",
            ],
            [
                'key' => 'balance_reminder',
                'label' => 'Balance reminder',
                'body' => "Good day {$name}, this is {$shop}. Your outstanding balance is {$balance}. Please pay when you can. Thank you.",
            ],
            [
                'key' => 'outfit_ready',
                'label' => 'Outfit ready',
                'body' => "Good day {$name}, this is {$shop}. {$outfitLine} Thank you.",
            ],
        ];
    }
}
