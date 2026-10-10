<?php

namespace App\Http\Controllers\Api;

use App\Enums\MeasurementUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Resources\UserResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'signature_name' => filled($data['signature_name'] ?? null) ? $data['signature_name'] : null,
        ]);

        return UserResource::make($request->user()->fresh()->load('business'));
    }

    public function uploadLogo(Request $request): UserResource
    {
        $business = $this->business($request);
        $request->validate([
            'logo' => ['required', 'image', 'max:800'],
        ]);

        if (filled($business->logo_path)) {
            Storage::disk('local')->delete($business->logo_path);
        }

        $path = $request->file('logo')->store('logos', 'local');
        $business->forceFill(['logo_path' => $path])->save();

        return UserResource::make($request->user()->fresh()->load('business'));
    }

    public function logo(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $dataUrl = self::logoDataUrl($business);

        if ($dataUrl === null) {
            return response()->json([
                'message' => 'No shop logo yet.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'data_url' => $dataUrl,
            ],
        ]);
    }

    public static function logoDataUrl(?Business $business): ?string
    {
        if ($business === null || ! filled($business->logo_path)) {
            return null;
        }

        if (! Storage::disk('local')->exists($business->logo_path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($business->logo_path);
        $mime = Storage::disk('local')->mimeType($business->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
