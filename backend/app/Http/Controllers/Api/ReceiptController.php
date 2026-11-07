<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\PhoneNormalizer;
use App\Services\ReceiptText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReceiptController extends Controller
{
    public function show(Customer $customer, Payment $payment): JsonResponse
    {
        $this->guard($customer, $payment);

        $payment->load(['business.user', 'customer', 'job']);
        $document = ReceiptText::document($payment);
        $phone = $customer->whatsapp_phone ?: $customer->phone;
        $country = $payment->business?->country ?: 'NG';
        $number = PhoneNormalizer::forWhatsApp((string) $phone, $country);

        return response()->json([
            'data' => [
                ...$document,
                'email' => $customer->email,
                'whatsapp_url' => $number === ''
                    ? null
                    : PhoneNormalizer::whatsappUrl((string) $phone, $country, (string) $document['caption']),
            ],
        ]);
    }

    public function email(Request $request, Customer $customer, Payment $payment): JsonResponse
    {
        $this->guard($customer, $payment);

        $data = $request->validate([
            'image' => ['required', 'string', 'max:1500000'],
        ]);

        $binary = base64_decode($data['image'], true);

        if ($binary === false || strlen($binary) < 32) {
            return response()->json([
                'message' => 'The receipt image could not be read.',
            ], 422);
        }

        $payment->load(['business.user', 'customer', 'job']);
        $account = trim((string) $payment->business?->user?->email);
        $customerEmail = trim((string) $customer->email);
        $sentToAccount = ReceiptText::sendToAccount($payment, $binary);
        $sentToCustomer = false;

        if (
            $customerEmail !== ''
            && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)
            && strcasecmp($customerEmail, $account) !== 0
        ) {
            $document = ReceiptText::document($payment);
            $shop = $document['shop_name'];
            $number = $document['receipt_number'];

            try {
                Mail::raw((string) $document['caption'], function ($message) use ($customerEmail, $shop, $number, $binary) {
                    $message->to($customerEmail)
                        ->subject("Receipt {$number} from {$shop}")
                        ->attachData($binary, "{$number}.png", ['mime' => 'image/png']);
                });
                $sentToCustomer = true;
            } catch (\Throwable) {
                $sentToCustomer = false;
            }
        }

        if (! $sentToAccount && ! $sentToCustomer) {
            return response()->json([
                'message' => 'Could not send the email. Check the shop mail settings.',
            ], 422);
        }

        $who = $sentToAccount ? $account : $customerEmail;
        if ($sentToAccount && $sentToCustomer) {
            $who = "{$account} and {$customerEmail}";
        }

        return response()->json([
            'message' => "Receipt sent to {$who}.",
        ]);
    }

    private function guard(Customer $customer, Payment $payment): void
    {
        abort_unless($payment->customer_id === $customer->id, 404);
        $this->authorize('view', $payment);
    }
}
