<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmUsageRecord extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost' => 'decimal:8',
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FootballFixture, LlmUsageRecord> */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(FootballFixture::class, 'football_fixture_id');
    }

    /** @return BelongsTo<AutomationRun, LlmUsageRecord> */
    public function automationRun(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class);
    }
}
