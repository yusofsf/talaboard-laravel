<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\DetectSuspiciousInput;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogRequest;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Must wrap the web group too, so redirects and the health endpoint receive these headers.
        $middleware->append(SecurityHeaders::class);
        $middleware->web(prepend: [
            ForceHttps::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            UpdateLastSeen::class,
            DetectSuspiciousInput::class,
            LogRequest::class,
        ]);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
