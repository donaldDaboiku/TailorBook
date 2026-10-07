<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $shops = User::query()
            ->where('role', UserRole::Tailor)
            ->with('business')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhereHas('business', function ($business) use ($search) {
                            $business->where('name', 'ilike', "%{$search}%")
                                ->orWhere('phone', 'ilike', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (User $user) => $this->shopPayload($user))
            ->values();

        return response()->json([
            'data' => $shops,
        ]);
    }

    public function suspend(User $user): JsonResponse
    {
        abort_unless($user->role === UserRole::Tailor, 404);

        $user->forceFill(['suspended_at' => now()])->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Shop suspended.',
            'data' => $this->shopPayload($user->fresh()->load('business')),
        ]);
    }

    public function unsuspend(User $user): JsonResponse
    {
        abort_unless($user->role === UserRole::Tailor, 404);

        $user->forceFill(['suspended_at' => null])->save();

        return response()->json([
            'message' => 'Shop activated.',
            'data' => $this->shopPayload($user->fresh()->load('business')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shopPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'owner_name' => $user->name,
            'email' => $user->email,
            'shop_name' => $user->business?->name,
            'phone' => $user->business?->phone,
            'country' => $user->business?->country,
            'created_at' => $user->created_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'suspended' => $user->isSuspended(),
            'suspended_at' => $user->suspended_at?->toIso8601String(),
        ];
    }
}
