<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Throwable;

class Handler extends ExceptionHandler
{
    // 其他現有方法...

    /**
     * 自定義未認證請求的響應
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // 如果是 API 請求，返回 JSON 響應而非重定向
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => '未認證'], 401);
        }

        return redirect()->guest(route('login'));
    }
}