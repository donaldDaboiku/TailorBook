<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ShopReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $body = trim($data['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Tell us how TailorMate helps your shop.',
            ]);
        }

        $review = ShopReview::query()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'rating' => (int) $data['rating'],
                'body' => $body,
                'status' => 'pending',
                'submitted_at' => now(),
                'moderated_at' => null,
                'moderated_by' => null,
                'rejection_reason' => null,
            ],
        );

        return response()->json([
            'message' => 'Submitted. Status: Pending.',
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
            'status' => $review->status,
            'status_label' => $review->statusLabel(),
            'rejection_reason' => $review->rejection_reason,
            'submitted_at' => $review->submitted_at?->toIso8601String(),
        ];
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
