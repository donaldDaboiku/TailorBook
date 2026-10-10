<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\PhoneNormalizer;
use App\Services\ReceiptText;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ReceiptController extends Controller
{
    public function show(Customer $customer, Payment $payment): JsonResponse
    {
        $this->guard($customer, $payment);

        $payment->load(['business', 'customer', 'job']);
        $phone = $customer->whatsapp_phone ?: $customer->phone;
        $country = $payment->business?->country ?: 'NG';
        $text = ReceiptText::for($payment);

        return response()->json([
            'data' => [
                'text' => $text,
                'email' => $customer->email,
                'whatsapp_url' => PhoneNormalizer::whatsappUrl($phone, $country, $text),
            ],
        ]);
    }

    public function email(Customer $customer, Payment $payment): JsonResponse
    {
        $this->guard($customer, $payment);

        $email = trim((string) $customer->email);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'message' => 'Add an email on this customer before sending a receipt.',
            ], 422);
        }

        $payment->load(['business', 'customer', 'job']);
        $shop = $payment->business?->name ?? 'your shop';
        $text = ReceiptText::for($payment);

        try {
            Mail::raw($text, function ($message) use ($email, $shop) {
                $message->to($email)->subject("Receipt from {$shop}");
            });
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Could not send the email. Check the shop mail settings.',
            ], 422);
        }

        return response()->json([
            'message' => "Receipt sent to {$email}.",
        ]);
    }

    private function guard(Customer $customer, Payment $payment): void
    {
        abort_unless($payment->customer_id === $customer->id, 404);
        $this->authorize('view', $payment);
    }
}
