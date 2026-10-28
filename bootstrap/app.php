<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Khai báo Middleware AdminAuth để bảo vệ các route của Admin
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
        ]);
        // Hủy đơn xác thực bằng mã ký trong header (không dựa vào cookie phiên),
        // nên vẫn chạy khi phiên server đã mất.
        $middleware->validateCsrfTokens(except: ['api/don-hang/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();