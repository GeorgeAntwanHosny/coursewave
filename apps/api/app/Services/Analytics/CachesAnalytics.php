<?php

namespace App\Services\Analytics;

use Closure;
use Illuminate\Support\Facades\Cache;

trait CachesAnalytics
{
    /**
     * Dashboard aggregates are recomputed at most every `analytics.cache_seconds`. 0 disables caching.
     * $maxSeconds lets a figure that should feel fresher (the attention list) use a shorter time;
     * it can only shorten the configured TTL, never lengthen it, so 0 still disables caching.
     */
    protected function remember(string $key, Closure $callback, ?int $maxSeconds = null): mixed
    {
        $ttl = (int) config('coursewave.analytics.cache_seconds', 300);

        if ($maxSeconds !== null) {
            $ttl = min($ttl, $maxSeconds);
        }

        return $ttl > 0 ? Cache::remember("analytics:{$key}", $ttl, $callback) : $callback();
    }
}
