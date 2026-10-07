<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'last_login_at',
    'suspended_at',
    'subscription_status',
    'subscribed_until',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
            'subscription_status' => SubscriptionStatus::class,
            'subscribed_until' => 'date',
        ];
    }

    public function business(): HasOne
    {
        return $this->hasOne(Business::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Effective access label for admin UI and login checks.
     */
    public function subscriptionAccessStatus(): string
    {
        if ($this->isAdmin()) {
            return 'admin';
        }

        $status = $this->subscription_status ?? SubscriptionStatus::Free;

        if ($status === SubscriptionStatus::Free) {
            return 'free';
        }

        if ($this->subscribed_until === null || $this->subscribed_until->copy()->endOfDay()->isPast()) {
            return 'expired';
        }

        return 'subscribed';
    }

    public function hasSubscriptionAccess(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($this->subscriptionAccessStatus(), ['free', 'subscribed'], true);
    }
}
