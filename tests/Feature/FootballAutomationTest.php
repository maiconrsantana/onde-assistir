<?php

namespace Tests\Feature;

use App\Contracts\FootballDataProvider;
use App\Exceptions\FootballDataProviderException;
use App\Services\Operations\FootballAutomationStatus;
use App\Services\Operations\PublicScheduleCache;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FootballAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_lists_daily_sync_and_broadcast_refresh(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('football:sync --days=14')
            ->expectsOutputToContain('football:refresh-broadcasts --days=7')
            ->assertExitCode(0);
    }

    public function test_successful_sync_invalidates_public_cache_and_records_status(): void
    {
        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');

        $this->app->bind(FootballDataProvider::class, fn () => new class implements FootballDataProvider
        {
            public function fixturesBetween(CarbonInterface $from, CarbonInterface $to): array
            {
                return [];
            }
        });

        $this->artisan('football:sync', [
            '--from' => '2026-09-18',
            '--to' => '2026-09-18',
        ])
            ->assertExitCode(0);

        $this->assertFalse(Cache::has(PublicScheduleCache::INDEX_KEY));
        $this->assertSame('success', app(FootballAutomationStatus::class)->statusFor('football:sync')['last_status']);
    }

    public function test_failed_sync_keeps_public_cache_and_records_failure(): void
    {
        Cache::put(PublicScheduleCache::INDEX_KEY, 'stale');

        $this->app->bind(FootballDataProvider::class, fn () => new class implements FootballDataProvider
        {
            public function fixturesBetween(CarbonInterface $from, CarbonInterface $to): array
            {
                throw FootballDataProviderException::requestFailed(429, 'rate limited');
            }
        });

        $this->artisan('football:sync', [
            '--from' => '2026-09-18',
            '--to' => '2026-09-18',
        ])
            ->assertExitCode(1);

        $this->assertSame('stale', Cache::get(PublicScheduleCache::INDEX_KEY));
        $this->assertSame('failure', app(FootballAutomationStatus::class)->statusFor('football:sync')['last_status']);
    }

    public function test_automation_status_command_displays_recorded_state(): void
    {
        app(FootballAutomationStatus::class)->recordSuccess('football:sync');

        $this->artisan('football:automation-status')
            ->expectsOutputToContain('football:sync')
            ->expectsOutputToContain('Atualização')
            ->assertExitCode(0);

        $this->assertSame('success', app(FootballAutomationStatus::class)->statusFor('football:sync')['last_status']);
    }

    public function test_automation_status_detects_stale_or_failed_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00', 'America/Sao_Paulo'));
        $status = app(FootballAutomationStatus::class);

        $status->recordSuccess('football:sync');

        $this->assertTrue($status->isFresh('football:sync'));

        Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00', 'America/Sao_Paulo'));
        $this->assertFalse($status->isFresh('football:sync'));

        $status->recordFailure('football:sync', 'Falha de teste.');
        $this->assertFalse($status->isFresh('football:sync'));

        Carbon::setTestNow();
    }
}
