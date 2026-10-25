<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ShopReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopReviewController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $review = ShopReview::query()->where('business_id', $business->id)->first();

        return response()->json([
            'data' => $review instanceof ShopReview ? $this->payload($review) : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'max:400'],
        ]);

        $review = ShopReview::query()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'rating' => (int) $data['rating'],
                'body' => trim($data['body']),
                'approved' => false,
            ],
        );

        return response()->json([
            'message' => 'Saved. It stays off the site until the admin posts it.',
            'data' => $this->payload($review),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ShopReview $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'body' => $review->body,
            'approved' => $review->approved,
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
