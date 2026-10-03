<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Business;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $business = $this->business($request);

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->when($request->string('q')->trim()->toString(), function ($query, string $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('whatsapp_phone', 'ilike', "%{$search}%");
                });
            })
            ->when($request->filled('gender'), fn ($query) => $query->where('gender', $request->string('gender')))
            ->orderBy('name')
            ->limit(100)
            ->get();

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validated();

        $customer = Customer::query()->create([
            ...$data,
            'business_id' => $business->id,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? $data['phone'],
        ]);

        return CustomerResource::make($customer)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        $resource = CustomerResource::make($customer);
        $resource->includeFinance = true;

        return $resource;
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $data = $request->validated();

        $customer->update([
            ...$data,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? $data['phone'],
        ]);

        return CustomerResource::make($customer->fresh());
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return response()->json([
            'message' => 'Customer archived.',
        ]);
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
