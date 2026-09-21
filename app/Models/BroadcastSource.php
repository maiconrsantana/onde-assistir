<?php

namespace App\Models;

use Database\Factories\BroadcastSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastSource extends Model
{
    /** @use HasFactory<BroadcastSourceFactory> */
    use HasFactory;

    public const PROVIDER_THESPORTSDB = 'sportsdb';

    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_GEMINI = 'gemini';

    public const PROVIDER_MANUAL = 'manual';

    public const RESULT_FOUND = 'found';

    public const RESULT_NOT_FOUND = 'not_found';

    public const RESULT_UNCERTAIN = 'uncertain';

    public const RESULT_CONFLICTING = 'conflicting';

    public const RESULT_ERROR = 'error';

    protected $guarded = [];

    /**
     * @return BelongsTo<FootballFixture, BroadcastSource>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(FootballFixture::class, 'football_fixture_id');
    }

    /**
     * @return HasMany<FixtureBroadcast>
     */
    public function fixtureBroadcasts(): HasMany
    {
        return $this->hasMany(FixtureBroadcast::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'evidence' => 'array',
            'raw_response' => 'array',
            'tokens_used' => 'integer',
            'web_search_calls' => 'integer',
            'provider_confidence' => 'float',
            'calculated_confidence' => 'float',
            'selected' => 'boolean',
            'queried_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }
}
