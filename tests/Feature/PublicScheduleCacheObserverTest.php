<?php

namespace Tests\Feature;

use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Services\Operations\PublicScheduleCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicScheduleCacheObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_fixture_invalidates_the_public_schedule_cache(): void
    {
        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');

        $fixture = FootballFixture::factory()->create();

        $this->assertFalse(Cache::has(PublicScheduleCache::INDEX_KEY));

        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');
        $fixture->update(['venue' => 'Estadio atualizado']);

        $this->assertFalse(Cache::has(PublicScheduleCache::INDEX_KEY));
    }

    public function test_saving_and_deleting_a_broadcast_invalidates_the_public_schedule_cache(): void
    {
        $broadcast = FixtureBroadcast::factory()->create();

        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');
        $broadcast->update(['notes' => 'Transmissao confirmada manualmente']);

        $this->assertFalse(Cache::has(PublicScheduleCache::INDEX_KEY));

        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');
        $broadcast->delete();

        $this->assertFalse(Cache::has(PublicScheduleCache::INDEX_KEY));
    }
}
