<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CatalogueItem;
use App\Services\Money;
use App\Services\PaystackBilling;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogueController extends Controller
{
    public function index(Request $request, PaystackBilling $billing): JsonResponse
    {
        $business = $this->business($request);
        $used = CatalogueItem::query()->where('business_id', $business->id)->count();

        $items = CatalogueItem::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->limit($billing->catalogueLimit($business))
            ->get()
            ->map(fn (CatalogueItem $item) => $this->payload($item, $business->currency));

        return response()->json([
            'data' => $items,
            'allowance' => $this->allowance($business, $used, $billing),
        ]);
    }

    public function store(Request $request, PaystackBilling $billing): JsonResponse
    {
        $business = $this->business($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:1'],
            'image' => ['nullable', 'image', 'max:800'],
        ]);

        $item = DB::transaction(function () use ($business, $data) {
            $locked = Business::query()->whereKey($business->id)->lockForUpdate()->first();

            if (! $locked instanceof Business) {
                return false;
            }

            $limit = (int) config('services.paystack.catalogue_free') + (int) $locked->catalogue_extra;

            if (CatalogueItem::query()->where('business_id', $business->id)->count() >= $limit) {
                return null;
            }

            return CatalogueItem::query()->create([
                'business_id' => $business->id,
                'name' => trim($data['name']),
                'price' => Money::normalize($data['price']),
            ]);
        });

        if ($item === false) {
            abort(403, 'Set up your shop first.');
        }

        if (! $item instanceof CatalogueItem) {
            $business->refresh();
            $pack = $billing->cataloguePack();
            $limit = $billing->catalogueLimit($business);

            return response()->json([
                'message' => "This shop is full at {$limit} styles. {$pack['slots']} more cost {$pack['amount_label']}.",
                'catalogue_full' => true,
            ], 422);
        }

        $this->attachImage($item, $request);

        return response()->json([
            'data' => $this->payload($item, $business->currency),
        ], 201);
    }

    public function extra(Request $request, PaystackBilling $billing): JsonResponse
    {
        try {
            $checkout = $billing->initializeCatalogue($request->user());
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $checkout,
        ]);
    }

    public function image(Request $request, CatalogueItem $catalogueItem): StreamedResponse
    {
        $business = $this->business($request);
        abort_unless($catalogueItem->business_id === $business->id, 404);
        abort_unless(
            filled($catalogueItem->image_path) && Storage::disk('local')->exists($catalogueItem->image_path),
            404,
        );

        $disk = Storage::disk('local');
        abort_unless($disk instanceof FilesystemAdapter, 404);

        return $disk->response($catalogueItem->image_path);
    }

    public function destroy(Request $request, CatalogueItem $catalogueItem): JsonResponse
    {
        $business = $this->business($request);
        abort_unless($catalogueItem->business_id === $business->id, 404);

        if (filled($catalogueItem->image_path)) {
            Storage::disk('local')->delete($catalogueItem->image_path);
        }

        $catalogueItem->delete();

        return response()->json(['message' => 'Style removed.']);
    }

    private function attachImage(CatalogueItem $item, Request $request): void
    {
        $file = $request->file('image');

        if ($file === null) {
            return;
        }

        $path = $file->store('catalogue', 'local');

        if (! is_string($path)) {
            $item->delete();

            abort(422, 'The photo could not be saved.');
        }

        try {
            $item->forceFill(['image_path' => $path])->save();
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            $item->delete();

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CatalogueItem $item, string $currency): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'price' => (string) $item->price,
            'price_label' => Money::format((string) $item->price, $currency),
            'has_image' => filled($item->image_path) && Storage::disk('local')->exists($item->image_path),
        ];
    }

    /**
     * @return array{free: int, extra: int, limit: int, used: int, pack: int, pack_label: string, pack_enabled: bool}
     */
    private function allowance(Business $business, int $used, PaystackBilling $billing): array
    {
        $pack = $billing->cataloguePack();

        return [
            'free' => (int) config('services.paystack.catalogue_free'),
            'extra' => (int) $business->catalogue_extra,
            'limit' => $billing->catalogueLimit($business),
            'used' => $used,
            'pack' => $pack['slots'],
            'pack_label' => $pack['amount_label'],
            'pack_enabled' => $billing->configured(),
        ];
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
