<?php

namespace App\Http\Middleware;

use App\Jobs\RefreshSongViews;
use Closure;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class RefreshSongViewsThrottle
{
    public function handle($request, Closure $next)
    {
        $nextAt = Cache::get('song_views:next_at');

        if (! $nextAt || now()->greaterThanOrEqualTo($nextAt)) {
            if (Cache::add('song_views:lock', 1, 60)) {
                try {
                    Bus::dispatchSync(new RefreshSongViews);
                    Cache::put('song_views:next_at', now()->addDays(15));
                } finally {
                    Cache::forget('song_views:lock');
                }
            }
        }

        return $next($request);
    }
}
