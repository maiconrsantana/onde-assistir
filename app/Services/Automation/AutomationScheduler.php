<?php

namespace App\Services\Automation;

use App\Enums\AutomationKey;
use App\Enums\AutomationTriggerType;
use App\Jobs\RunAutomationJob;
use App\Models\AutomationSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class AutomationScheduler
{
    public function dispatchDue(): int
    {
        $now = CarbonImmutable::now(config('app.timezone'))->startOfMinute();
        $dispatched = 0;

        AutomationSetting::query()
            ->where('enabled', true)
            ->whereNotNull('schedule_time')
            ->get()
            ->each(function (AutomationSetting $setting) use ($now, &$dispatched): void {
                $days = array_map('intval', $setting->schedule_days ?? []);
                $time = substr((string) $setting->schedule_time, 0, 5);

                if (! in_array($now->dayOfWeekIso, $days, true) || $time !== $now->format('H:i')) {
                    return;
                }

                $lastRaw = $setting->getRawOriginal('last_dispatched_at');
                $lastDispatched = $lastRaw ? CarbonImmutable::parse($lastRaw, 'UTC') : null;
                if ($lastDispatched?->format('Y-m-d H:i') === $now->utc()->format('Y-m-d H:i')) {
                    return;
                }

                $lock = Cache::lock("automation.schedule.{$setting->key}.{$now->format('Y-m-d-H-i')}", 120);
                if (! $lock->get()) {
                    return;
                }

                try {
                    $key = AutomationKey::from($setting->key);
                    RunAutomationJob::dispatch(
                        $key,
                        $this->parametersFor($key),
                        null,
                        AutomationTriggerType::Scheduled,
                    );

                    $setting->forceFill([
                        'last_dispatched_at' => $now->utc(),
                        'next_run_at' => $this->nextRunAt($now, $days, (string) $setting->schedule_time),
                    ])->saveQuietly();
                    $dispatched++;
                } finally {
                    $lock->release();
                }
            });

        return $dispatched;
    }

    /** @return array<string, int> */
    private function parametersFor(AutomationKey $key): array
    {
        return match ($key) {
            AutomationKey::SyncFixtures => ['--days' => 14],
            AutomationKey::RefreshBroadcasts => ['--days' => 7],
            default => [],
        };
    }

    /** @param array<int, int> $days */
    private function nextRunAt(CarbonImmutable $now, array $days, string $time): CarbonImmutable
    {
        for ($offset = 1; $offset <= 7; $offset++) {
            $candidate = $now->addDays($offset)->setTimeFromTimeString($time);
            if (in_array($candidate->dayOfWeekIso, $days, true)) {
                return $candidate->utc();
            }
        }

        return $now->addWeek()->setTimeFromTimeString($time)->utc();
    }
}
