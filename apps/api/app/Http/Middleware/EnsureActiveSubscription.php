<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasActive = $request->user()?->subscriptions()->where('status', 'active')->exists();

        if (! $hasActive) {
            return ApiResponse::error(
                'An active subscription is required to access course content.',
                null,
                403
            );
        }

        return $next($request);
    }
}
