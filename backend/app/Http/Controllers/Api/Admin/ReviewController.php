<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ShopReview::query()
            ->with('business.user:id,name,email')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (ShopReview $review) => $this->payload($review))
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function update(Request $request, ShopReview $shopReview): JsonResponse
    {
        $data = $request->validate([
            'approved' => ['required', 'boolean'],
        ]);

        $shopReview->forceFill([
            'approved' => $data['approved'],
        ])->save();
        $shopReview->load('business.user:id,name,email');

        return response()->json([
            'message' => $shopReview->approved
                ? 'This review is on the site.'
                : 'This review is hidden.',
            'data' => $this->payload($shopReview),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ShopReview $review): array
    {
        $business = $review->business;

        return [
            'id' => $review->id,
            'shop_name' => $business?->name,
            'state' => $business?->state,
            'owner_email' => $business?->user?->email,
            'rating' => $review->rating,
            'body' => $review->body,
            'approved' => $review->approved,
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }
}
