<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;

class PublicScheduleCache
{
    public const INDEX_KEY = 'public.fixtures.index';

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    public function remember(callable $callback): mixed
    {
        return Cache::remember(self::INDEX_KEY, now()->addMinutes(10), $callback);
    }

    public function invalidate(): void
    {
        Cache::forget(self::INDEX_KEY);
    }
}
