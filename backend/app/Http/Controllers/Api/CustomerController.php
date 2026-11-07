<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Measurement;
use App\Models\Payment;
use App\Services\ShopRecords;
use App\Services\SyncRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('whatsapp_phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('gender'), fn ($query) => $query->where('gender', $request->string('gender')))
            ->orderBy('name')
            ->limit(100)
            ->get();

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request, SyncRecords $sync): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validated();
        $existing = $sync->replayCreate(Customer::class, $business->id, $data['id'] ?? null);

        if ($existing instanceof Customer) {
            return CustomerResource::make($existing)
                ->response()
                ->setStatusCode(200);
        }

        $customer = Customer::query()->create($sync->withClientId([
            ...$data,
            'business_id' => $business->id,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? $data['phone'],
        ], $data['id'] ?? null));

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

    public function update(UpdateCustomerRequest $request, Customer $customer, SyncRecords $sync): CustomerResource
    {
        $businessId = (string) $customer->business_id;
        $key = $request->header('Idempotency-Key');

        if ($sync->replayUpdate($businessId, $key, $customer->id)) {
            return CustomerResource::make($customer->fresh());
        }

        $data = $request->validated();
        $sync->logPrevious($businessId, $customer);

        $customer->update([
            ...$data,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? $data['phone'],
        ]);

        if (is_string($key)) {
            $sync->remember($businessId, $key, $customer);
        }

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

    public function erase(Request $request, string $customer): JsonResponse
    {
        $record = $this->customerRecord($request, $customer);

        DB::transaction(function () use ($record): void {
            Payment::withTrashed()->where('customer_id', $record->id)->forceDelete();
            CustomerJob::withTrashed()->where('customer_id', $record->id)->forceDelete();
            Measurement::withTrashed()->where('customer_id', $record->id)->forceDelete();
            $record->forceDelete();
        });

        return response()->json([
            'message' => 'Client erased.',
        ]);
    }

    public function records(Request $request, string $customer, ShopRecords $records): Response
    {
        $business = $this->business($request)->load('user');
        $record = $this->customerRecord($request, $customer);
        $filename = $records->filename($business, $record);

        return response($records->csv($business, $record), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function customerRecord(Request $request, string $customer): Customer
    {
        return Customer::withTrashed()
            ->where('business_id', $this->business($request)->id)
            ->whereKey($customer)
            ->firstOrFail();
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
