<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CatalogueItem;
use App\Services\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $business = $this->business($request);

        $items = CatalogueItem::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->limit(40)
            ->get()
            ->map(fn (CatalogueItem $item) => $this->payload($item, $business->currency));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->business($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:1'],
            'image' => ['nullable', 'image', 'max:800'],
        ]);

        $item = DB::transaction(function () use ($business, $data) {
            Business::query()->whereKey($business->id)->lockForUpdate()->first();

            if (CatalogueItem::query()->where('business_id', $business->id)->count() >= 40) {
                return null;
            }

            return CatalogueItem::query()->create([
                'business_id' => $business->id,
                'name' => trim($data['name']),
                'price' => Money::normalize($data['price']),
            ]);
        });

        if (! $item instanceof CatalogueItem) {
            return response()->json([
                'message' => 'This shop already has 40 styles. Remove one before adding another.',
            ], 422);
        }

        $this->attachImage($item, $request);

        return response()->json([
            'data' => $this->payload($item, $business->currency),
        ], 201);
    }

    public function image(Request $request, CatalogueItem $catalogueItem): StreamedResponse
    {
        $business = $this->business($request);
        abort_unless($catalogueItem->business_id === $business->id, 404);
        abort_unless(
            filled($catalogueItem->image_path) && Storage::disk('local')->exists($catalogueItem->image_path),
            404,
        );

        return Storage::disk('local')->response($catalogueItem->image_path);
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

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
