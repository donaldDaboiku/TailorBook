<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        ResetPassword::createUrlUsing(function (object $user, string $token) {
            $base = rtrim((string) config('app.frontend_url'), '/');

            return $base.'/?reset=1&token='.urlencode($token).'&email='.urlencode($user->email);
        });
    }
}
