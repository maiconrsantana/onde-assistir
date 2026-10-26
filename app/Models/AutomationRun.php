<?php

namespace App\Models;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTriggerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AutomationRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AutomationRunStatus::class,
            'trigger_type' => AutomationTriggerType::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'parameters' => 'array',
            'result' => 'array',
        ];
    }

    /** @return BelongsTo<User, AutomationRun> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function automationSetting(): BelongsTo
    {
        return $this->belongsTo(AutomationSetting::class, 'automation_key', 'key');
    }

    public function markSuccess(array $totals = []): void
    {
        $this->finish(AutomationRunStatus::Success, $totals);
    }

    public function markFailure(string $message, array $totals = []): void
    {
        $this->finish(AutomationRunStatus::Failed, $totals, $message);
    }

    /** @param array<string, mixed> $totals */
    private function finish(AutomationRunStatus $status, array $totals = [], ?string $message = null): void
    {
        $finishedAt = now()->utc();
        $startedAt = $this->getRawOriginal('started_at')
            ? Carbon::parse($this->getRawOriginal('started_at'), 'UTC')
            : null;

        $this->forceFill([
            'status' => $status,
            'finished_at' => $finishedAt,
            'duration_ms' => $startedAt?->diffInMilliseconds($finishedAt),
            'processed_count' => (int) ($totals['processed_count'] ?? $totals['processed'] ?? 0),
            'success_count' => (int) ($totals['success_count'] ?? $totals['succeeded'] ?? 0),
            'failure_count' => (int) ($totals['failure_count'] ?? $totals['failed'] ?? 0),
            'error_message' => $message,
            'result' => $totals,
        ])->saveQuietly();
    }
}
