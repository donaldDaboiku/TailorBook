<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CatalogueItem;
use App\Services\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        if (CatalogueItem::query()->where('business_id', $business->id)->count() >= 40) {
            return response()->json([
                'message' => 'This shop already has 40 styles. Remove one before adding another.',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:1'],
            'image' => ['nullable', 'image', 'max:800'],
        ]);

        $path = $request->file('image')?->store('catalogue', 'local');

        $item = CatalogueItem::query()->create([
            'business_id' => $business->id,
            'name' => trim($data['name']),
            'price' => Money::normalize($data['price']),
            'image_path' => $path,
        ]);

        return response()->json([
            'data' => $this->payload($item, $business->currency),
        ], 201);
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
            'image_url' => $this->imageUrl($item),
        ];
    }

    private function imageUrl(CatalogueItem $item): ?string
    {
        if (! filled($item->image_path) || ! Storage::disk('local')->exists($item->image_path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($item->image_path);

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
