<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Services\PhoneNormalizer;
use App\Services\WhatsAppMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function templates(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $business = $this->business($request);
        abort_unless($customer->business_id === $business->id, 403);

        $phone = $customer->whatsapp_phone ?: $customer->phone;
        $country = $business->country ?: 'NG';
        $number = PhoneNormalizer::forWhatsApp($phone, $country);
        $templates = WhatsAppMessages::forCustomer($customer, $business);

        return response()->json([
            'data' => [
                'phone' => $phone,
                'whatsapp_number' => $number,
                'whatsapp_url' => PhoneNormalizer::whatsappUrl($phone, $country),
                'templates' => array_map(
                    fn (array $template) => [
                        ...$template,
                        'url' => PhoneNormalizer::whatsappUrl($phone, $country, $template['body']),
                    ],
                    $templates,
                ),
            ],
        ]);
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
