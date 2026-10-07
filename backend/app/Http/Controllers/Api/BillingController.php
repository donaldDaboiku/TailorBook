<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\PaystackBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BillingController extends Controller
{
    public function plan(PaystackBilling $billing): JsonResponse
    {
        return response()->json([
            'data' => $billing->plan(),
        ]);
    }

    public function checkout(Request $request, PaystackBilling $billing): JsonResponse
    {
        try {
            $checkout = $billing->initialize($request->user());
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $checkout,
        ]);
    }

    public function verify(Request $request, PaystackBilling $billing): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        try {
            $user = $billing->verifyAndActivate($data['reference'], $request->user());
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Subscription activated.',
            'data' => UserResource::make($user),
        ]);
    }

    public function webhook(Request $request, PaystackBilling $billing): JsonResponse
    {
        try {
            $billing->handleWebhook(
                $request->getContent(),
                $request->header('x-paystack-signature'),
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json(['status' => true]);
    }
}
