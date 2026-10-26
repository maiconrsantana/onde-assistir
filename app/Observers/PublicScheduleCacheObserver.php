<?php

namespace App\Observers;

use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Services\Operations\PublicScheduleCache;

class PublicScheduleCacheObserver
{
    public function saved(FootballFixture|FixtureBroadcast $model): void
    {
        app(PublicScheduleCache::class)->invalidate();
    }

    public function deleted(FootballFixture|FixtureBroadcast $model): void
    {
        app(PublicScheduleCache::class)->invalidate();
    }
}
