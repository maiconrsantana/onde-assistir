<?php

namespace Tests\Feature;

use App\Enums\AutomationKey;
use App\Enums\AutomationRunStatus;
use App\Models\AutomationSetting;
use App\Services\Automation\AutomationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class AutomationRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        foreach (AutomationKey::cases() as $key) {
            Cache::lock('automation.run.'.$key->value)->forceRelease();
        }
    }

    public function test_runner_records_a_successful_execution_and_totals(): void
    {
        $run = app(AutomationRunner::class)->run(
            AutomationKey::SyncFixtures,
            fn (): array => [
                'processed_count' => 3,
                'success_count' => 2,
                'failure_count' => 1,
            ],
            ['from' => '2026-10-17', 'to' => '2026-10-24'],
        );

        $this->assertSame(AutomationRunStatus::Success, $run->status);
        $this->assertSame(3, $run->processed_count);
        $this->assertSame(2, $run->success_count);
        $this->assertSame(1, $run->failure_count);
        $this->assertNotNull($run->finished_at);
        $this->assertSame('dailyAt:06:00', $run->automationSetting?->schedule);
    }

    public function test_disabled_automation_is_skipped_without_running_the_task(): void
    {
        AutomationSetting::ensureFor(AutomationKey::ResolveGeminiBroadcasts)
            ->update(['enabled' => false]);

        $executed = false;
        $run = app(AutomationRunner::class)->run(
            AutomationKey::ResolveGeminiBroadcasts,
            function () use (&$executed): array {
                $executed = true;

                return [];
            },
        );

        $this->assertFalse($executed);
        $this->assertSame(AutomationRunStatus::Skipped, $run->status);
        $this->assertSame('Automacao desabilitada.', $run->error_message);
    }

    public function test_failed_execution_is_recorded_and_exception_is_rethrown(): void
    {
        try {
            app(AutomationRunner::class)->run(
                AutomationKey::RefreshBroadcasts,
                function (): array {
                    throw new RuntimeException('Falha externa simulada.');
                },
            );

            $this->fail('A excecao da automacao deveria ser relancada.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha externa simulada.', $exception->getMessage());
        }

        $this->assertDatabaseHas('automation_runs', [
            'automation_key' => AutomationKey::RefreshBroadcasts->value,
            'status' => AutomationRunStatus::Failed->value,
            'error_message' => 'Falha externa simulada.',
        ]);
    }

    public function test_duration_is_positive_when_app_timezone_differs_from_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 15:00:00', 'America/Sao_Paulo'));

        $run = app(AutomationRunner::class)->run(
            AutomationKey::SyncFixtures,
            fn (): array => [],
        );

        $this->assertGreaterThanOrEqual(0, $run->duration_ms);
    }
}
