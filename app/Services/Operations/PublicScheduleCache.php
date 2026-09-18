<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;

class PublicScheduleCache
{
    public const INDEX_KEY = 'public.fixtures.index';

    public function invalidate(): void
    {
        Cache::forget(self::INDEX_KEY);
    }
}
