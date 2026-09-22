<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('correo')).'|'.$request->ip());
        });

        RateLimiter::for('auth-refresh', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
