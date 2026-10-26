<?php

namespace Tests\Feature;

use App\Enums\AutomationKey;
use App\Jobs\RunAutomationJob;
use App\Models\AutomationSetting;
use App\Services\Automation\AutomationScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_automation_is_queued_once_for_configured_day_and_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 06:00:00', 'America/Sao_Paulo'));
        Queue::fake();

        AutomationSetting::ensureFor(AutomationKey::SyncFixtures)->update([
            'schedule_days' => [1],
            'schedule_time' => '06:00:00',
            'enabled' => true,
        ]);

        $scheduler = app(AutomationScheduler::class);

        $this->assertSame(1, $scheduler->dispatchDue());
        $this->assertSame(0, $scheduler->dispatchDue());
        Queue::assertPushed(RunAutomationJob::class, 1);
        $this->assertNotNull(AutomationSetting::query()->where('key', AutomationKey::SyncFixtures->value)->value('last_dispatched_at'));

        Carbon::setTestNow();
    }
}
