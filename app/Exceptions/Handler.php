<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Auth\AuthenticationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * 自定義未認證請求的響應
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // 如果是 API 請求，返回 JSON 響應而非重定向
        if ($request->expectsJson() || $request->is('api/*') || str_starts_with($request->path(), 'api/')) {
            return response()->json(['message' => '未認證'], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // 捕获MethodNotAllowedHttpException异常，特别是与api/login相关的
        if ($e instanceof MethodNotAllowedHttpException && 
            str_contains($request->url(), 'api/login')) {
            return response()->json(['message' => '未认证，请使用POST方法登录'], 401);
        }

        return parent::render($request, $e);
    }
}