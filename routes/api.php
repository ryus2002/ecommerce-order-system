<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// 公開 API 路由（不需要認證）
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// 需要認證的 API 路由
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // 訂單路由
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    // 其他訂單路由...
});
