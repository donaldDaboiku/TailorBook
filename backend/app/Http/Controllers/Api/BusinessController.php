<?php

namespace App\Http\Controllers\Api;

use App\Enums\MeasurementUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Resources\UserResource;
use App\Models\Business;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function update(UpdateBusinessRequest $request): UserResource
    {
        $business = $this->business($request);
        $data = $request->validated();
        $phone = $data['phone'];

        $business->update([
            'name' => $data['name'],
            'phone' => $phone,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? $phone,
            'country' => strtoupper($data['country']),
            'measurement_unit' => MeasurementUnit::from($data['measurement_unit']),
            'currency' => strtoupper($data['currency']),
            'receipt_header' => filled($data['receipt_header'] ?? null) ? $data['receipt_header'] : null,
            'receipt_footer' => filled($data['receipt_footer'] ?? null) ? $data['receipt_footer'] : null,
        ]);

        return UserResource::make($request->user()->fresh()->load('business'));
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
