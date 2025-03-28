<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 在 Laravel 12 中，路由已在 bootstrap/app.php 中注册，此处不再重复注册
        // $this->routes(function () {
        //     // API 路由
        //     Route::middleware('api')
        //         ->prefix('api')
        //         ->group(base_path('routes/api.php'));
        //
        //     // Web 路由
        //     Route::middleware('web')
        //         ->group(base_path('routes/web.php'));
        // });
    }
}