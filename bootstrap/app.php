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
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth:sanctum' => \App\Http\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            // Handle authentication exceptions for API requests
            if ($e instanceof \Illuminate\Auth\AuthenticationException && ($request->expectsJson() || $request->is('api/*'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado. Por favor, inicie sesión.'
                ], 401);
            }

            // Handle HTTP exceptions for API requests
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && ($request->expectsJson() || $request->is('api/*'))) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], $e->getStatusCode());
            }
        });
    })->create();
