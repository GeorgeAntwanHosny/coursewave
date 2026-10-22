<?php

use Illuminate\Foundation\Application;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'subscription.active' => \App\Http\Middleware\EnsureActiveSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 1. Force JSON responses for API routes or JSON-accepting clients
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // 2. Format all API exceptions through ApiResponse::error()
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null; // Fallback to Laravel's default web rendering
            }

            // Validation Failures (422)
            if ($e instanceof ValidationException) {
                return ApiResponse::error(
                    message: $e->getMessage(),
                    errors: $e->errors(),
                    status: 422
                );
            }

            // Unauthenticated (401)
            if ($e instanceof AuthenticationException) {
                return ApiResponse::error(
                    message: $e->getMessage() ?: 'Unauthenticated.',
                    errors: null,
                    status: 401
                );
            }

            // HTTP Exceptions e.g., 404 Model/Route Not Found, 403 Forbidden, 405 Method Not Allowed
            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    message: $e->getMessage() ?: 'HTTP Request Error',
                    errors: null,
                    status: $e->getStatusCode()
                );
            }

            // Generic / Server Errors (500)
            $statusCode = 500;
            $message = config('app.debug') ? $e->getMessage() : 'Server Error.';
            $debugDetails = config('app.debug') ? [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => collect($e->getTrace())->take(5)->toArray(),
            ] : null;

            return ApiResponse::error(
                message: $message,
                errors: $debugDetails,
                status: $statusCode
            );
        });
    })
    ->create();
