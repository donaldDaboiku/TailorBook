<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value ?? 'tailor',
            'subscription_status' => $this->subscription_status?->value ?? 'free',
            'subscription_access' => $this->subscriptionAccessStatus(),
            'subscribed_until' => $this->subscribed_until?->toDateString(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'business' => BusinessResource::make($this->whenLoaded('business')),
        ];
    }
}
