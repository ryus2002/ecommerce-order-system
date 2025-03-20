<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 設置測試環境的應用程式金鑰
        $this->app['config']->set('app.key', 'base64:'.base64_encode(Str::random(32)));

        // 全局禁用Sanctum
        $this->withoutMiddleware([
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Auth\Middleware\Authenticate::class
        ]);
    }
    
    protected function tearDown(): void
    {
        // 確保沒有懸掛的交易
        if ($this->app && $this->app['db']->transactionLevel() > 0) {
            $this->app['db']->rollBack();
        }
        
        if ($this->app) {
            $this->app['db']->disconnect();
        }
        
        parent::tearDown();
    }
}
