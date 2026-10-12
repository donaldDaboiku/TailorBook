<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackMessage;
use Illuminate\Http\JsonResponse;

class FeedbackController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = FeedbackMessage::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (FeedbackMessage $row) => [
                'id' => $row->id,
                'name' => $row->name,
                'contact' => $row->contact,
                'message' => $row->message,
                'shop_email' => $row->user?->email,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }
}
