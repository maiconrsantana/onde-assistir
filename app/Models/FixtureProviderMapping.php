<?php

namespace App\Models;

use Database\Factories\FixtureProviderMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixtureProviderMapping extends Model
{
    /** @use HasFactory<FixtureProviderMappingFactory> */
    use HasFactory;

    public const PROVIDER_API_FOOTBALL = 'api_football';

    public const PROVIDER_FOOTBALL_DATA = 'football_data';

    public const PROVIDER_THESPORTSDB = 'sportsdb';

    protected $guarded = [];

    /**
     * @return BelongsTo<FootballFixture, FixtureProviderMapping>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(FootballFixture::class, 'football_fixture_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_score' => 'float',
            'matched_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
}
