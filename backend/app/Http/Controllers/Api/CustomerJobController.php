<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\StoreCustomerJobRequest;
use App\Http\Resources\CustomerJobResource;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Services\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerJobController extends Controller
{
    public function index(Request $request, Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [CustomerJob::class, $customer]);

        $jobs = CustomerJob::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('service_date')
            ->orderByDesc('created_at')
            ->get();

        return CustomerJobResource::collection($jobs);
    }

    public function store(StoreCustomerJobRequest $request, Customer $customer): JsonResponse
    {
        $business = $request->user()?->business;
        abort_unless($business !== null, 403, 'Set up your shop first.');

        $data = $request->validated();

        $job = CustomerJob::query()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'title' => $data['title'],
            'agreed_amount' => Money::normalize($data['agreed_amount']),
            'service_date' => $data['service_date'],
        ]);

        return CustomerJobResource::make($job)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Customer $customer, CustomerJob $job): CustomerJobResource
    {
        abort_unless($job->customer_id === $customer->id, 404);
        $this->authorize('view', $job);

        return CustomerJobResource::make($job);
    }

    public function destroy(Request $request, Customer $customer, CustomerJob $job): JsonResponse
    {
        abort_unless($job->customer_id === $customer->id, 404);
        $this->authorize('delete', $job);

        $job->delete();

        return response()->json([
            'message' => 'Job archived.',
        ]);
    }
}
