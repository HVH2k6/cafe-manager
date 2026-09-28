<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ngrok (dev) and Nginx (production) run on this machine and forward HTTPS as HTTP:
        // trust their X-Forwarded-* headers so generated URLs use https and the public host.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->web(append: [EnsureUserIsActive::class]);
        $middleware->validateCsrfTokens(except: ['webhooks/payos']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
