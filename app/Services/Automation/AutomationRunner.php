<?php

namespace App\Services\Automation;

use App\Enums\AutomationKey;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTriggerType;
use App\Exceptions\AutomationExecutionException;
use App\Models\AutomationRun;
use App\Models\AutomationSetting;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AutomationRunner
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  Closure(AutomationRun): array<string, mixed>  $task
     */
    public function run(
        AutomationKey $key,
        Closure $task,
        array $parameters = [],
        ?User $user = null,
        AutomationTriggerType $triggerType = AutomationTriggerType::Manual,
    ): AutomationRun {
        $setting = AutomationSetting::ensureFor($key);

        if (! $setting->enabled) {
            return $this->createSkippedRun(
                $key,
                $parameters,
                $user,
                $triggerType,
                'Automacao desabilitada.',
            );
        }

        $lock = Cache::lock($this->lockKey($key), 900);

        if (! $lock->get()) {
            return $this->createSkippedRun(
                $key,
                $parameters,
                $user,
                $triggerType,
                'Ja existe uma execucao em andamento.',
            );
        }

        $startedAt = now()->utc();
        $run = AutomationRun::query()->create([
            'automation_key' => $key->value,
            'trigger_type' => $triggerType,
            'status' => AutomationRunStatus::Running,
            'started_at' => $startedAt,
            'parameters' => $parameters,
            'user_id' => $user?->getKey(),
        ]);

        $setting->forceFill(['last_run_at' => $startedAt])->saveQuietly();
        app(AutomationRunContext::class)->set($run->getKey());

        try {
            $run->markSuccess($task($run));

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailure(
                $exception->getMessage(),
                $exception instanceof AutomationExecutionException ? $exception->result() : [],
            );

            throw $exception;
        } finally {
            app(AutomationRunContext::class)->clear();
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function createSkippedRun(
        AutomationKey $key,
        array $parameters,
        ?User $user,
        AutomationTriggerType $triggerType,
        string $message,
    ): AutomationRun {
        $now = now()->utc();

        return AutomationRun::query()->create([
            'automation_key' => $key->value,
            'trigger_type' => $triggerType,
            'status' => AutomationRunStatus::Skipped,
            'started_at' => $now,
            'finished_at' => $now,
            'duration_ms' => 0,
            'parameters' => $parameters,
            'error_message' => $message,
            'user_id' => $user?->getKey(),
        ]);
    }

    private function lockKey(AutomationKey $key): string
    {
        return 'automation.run.'.$key->value;
    }
}
