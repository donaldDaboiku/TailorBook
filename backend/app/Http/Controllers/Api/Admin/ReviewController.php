<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', '');
        $term = trim((string) $request->query('q', ''));

        $rows = ShopReview::query()
            ->with('business.user:id,name,email')
            ->when(in_array($status, ['pending', 'published', 'rejected'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('body', 'ilike', $like)
                        ->orWhereHas('business', function ($business) use ($like) {
                            $business->where('name', 'ilike', $like)
                                ->orWhereHas('user', function ($user) use ($like) {
                                    $user->where('email', 'ilike', $like);
                                });
                        });
                });
            })
            ->orderByDesc('submitted_at')
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
            'action' => ['required', 'in:approve,reject,hide'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $reason = trim((string) ($data['reason'] ?? ''));

        $status = match ($data['action']) {
            'approve' => 'published',
            'reject' => 'rejected',
            default => 'pending',
        };

        $shopReview->forceFill([
            'status' => $status,
            'rejection_reason' => $status === 'rejected' && $reason !== '' ? $reason : null,
            'moderated_at' => now(),
            'moderated_by' => $request->user()?->id,
        ])->save();
        $shopReview->load('business.user:id,name,email');

        $message = match ($status) {
            'published' => 'This review is on the site.',
            'rejected' => 'This review was not approved.',
            default => 'This review is hidden.',
        };

        return response()->json([
            'message' => $message,
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
            'status' => $review->status,
            'status_label' => $review->statusLabel(),
            'rejection_reason' => $review->rejection_reason,
            'submitted_at' => $review->submitted_at?->toIso8601String(),
            'moderated_at' => $review->moderated_at?->toIso8601String(),
        ];
    }
}
