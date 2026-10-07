<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $user, string $token) {
            $base = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

            return $base.'/?reset=1&token='.urlencode($token).'&email='.urlencode($user->email);
        });
    }
}
