<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Money;
use App\Services\ReceiptText;
use App\Services\SyncRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(Request $request, Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Payment::class, $customer]);

        $payments = Payment::query()
            ->where('customer_id', $customer->id)
            ->with('job')
            ->orderByDesc('paid_on')
            ->orderByDesc('created_at')
            ->get();

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request, Customer $customer, SyncRecords $sync): JsonResponse
    {
        $business = $request->user()?->business;
        abort_unless($business !== null, 403, 'Set up your shop first.');

        $data = $request->validated();
        $existing = $sync->replayCreate(Payment::class, $business->id, $data['id'] ?? null);

        if ($existing instanceof Payment) {
            abort_unless($existing->customer_id === $customer->id, 404);

            return PaymentResource::make($existing->load('job'))
                ->response()
                ->setStatusCode(200);
        }

        $payment = Payment::query()->create($sync->withClientId([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'customer_job_id' => $data['customer_job_id'] ?? null,
            'amount' => Money::normalize($data['amount']),
            'paid_on' => $data['paid_on'],
            'method' => PaymentMethod::from($data['method']),
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
        ], $data['id'] ?? null))->load('job');

        ReceiptText::sendToAccount($payment);

        return PaymentResource::make($payment)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Customer $customer, Payment $payment): JsonResponse
    {
        abort_unless($payment->customer_id === $customer->id, 404);
        $this->authorize('delete', $payment);

        $payment->delete();

        return response()->json([
            'message' => 'Payment archived.',
        ]);
    }
}
