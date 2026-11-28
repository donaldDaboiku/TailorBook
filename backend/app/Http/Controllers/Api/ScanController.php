<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Services\MeasurementTemplates;
use App\Services\SlipOcr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ScanController extends Controller
{
    public function slip(Request $request, SlipOcr $ocr): JsonResponse
    {
        $business = $this->business($request);

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'max:4096'],
        ]);

        try {
            $draft = $ocr->read($validated['image']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $matched = $this->matchCustomer($business, $draft['phone']);
        $templates = MeasurementTemplates::forBusiness($business, true);
        $template = $templates->firstWhere('slug', $draft['template_slug'])
            ?? $templates->first();

        $allowedKeys = collect($template['fields'] ?? [])->pluck('key')->all();
        $measurements = collect($draft['measurements'])
            ->only($allowedKeys)
            ->all();

        return response()->json([
            'data' => [
                'name' => $draft['name'],
                'phone' => $draft['phone'],
                'gender' => $draft['gender'],
                'style' => $draft['style'],
                'note' => $draft['note'],
                'template_slug' => $template['slug'] ?? $draft['template_slug'],
                'unit' => $draft['unit'],
                'measurements' => $measurements,
                'matched_customer' => $matched === null ? null : [
                    'id' => $matched->id,
                    'name' => $matched->name,
                    'phone' => $matched->phone,
                ],
            ],
        ]);
    }

    private function matchCustomer(Business $business, ?string $phone): ?Customer
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $needle = $this->phoneTail($phone);

        // Empty needle must not match blank customer phone/WhatsApp tails ('' === '').
        if ($needle === '') {
            return null;
        }

        return Customer::query()
            ->where('business_id', $business->id)
            ->get(['id', 'name', 'phone', 'whatsapp_phone'])
            ->first(function (Customer $customer) use ($needle) {
                $phoneTail = $this->phoneTail((string) $customer->phone);
                if ($phoneTail !== '' && $phoneTail === $needle) {
                    return true;
                }

                $whatsapp = trim((string) $customer->whatsapp_phone);
                if ($whatsapp === '') {
                    return false;
                }

                $whatsappTail = $this->phoneTail($whatsapp);

                return $whatsappTail !== '' && $whatsappTail === $needle;
            });
    }

    private function phoneTail(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
