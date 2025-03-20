<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('redis.pool', function () {
            $pool = new \Mix\Redis\ConnectionPool([
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD', null),
                'database' => env('REDIS_DB', 0),
                'max_active' => 100,
                'max_idle' => 20,
                'max_idle_time' => 60,
                'wait_timeout' => 3.0,
            ]);
            return $pool;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
