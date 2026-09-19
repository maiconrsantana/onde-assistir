<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Database\Factories\FootballFixtureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FootballFixture extends Model
{
    /** @use HasFactory<FootballFixtureFactory> */
    use HasFactory;

    public const RESOLUTION_PENDING = 'pending';

    public const RESOLUTION_PROCESSING = 'processing';

    public const RESOLUTION_RESOLVED = 'resolved';

    public const RESOLUTION_NOT_FOUND = 'not_found';

    public const RESOLUTION_UNCERTAIN = 'uncertain';

    public const RESOLUTION_CONFLICTING = 'conflicting';

    public const RESOLUTION_ERROR = 'error';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    public const PUBLICATION_DRAFT = 'draft';

    public const PUBLICATION_PUBLISHED = 'published';

    public const PUBLICATION_UNPUBLISHED = 'unpublished';

    protected $guarded = [];

    /**
     * @return BelongsTo<Competition, FootballFixture>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Team, FootballFixture>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, FootballFixture>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * @return BelongsTo<User, FootballFixture>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<FixtureBroadcast>
     */
    public function fixtureBroadcasts(): HasMany
    {
        return $this->hasMany(FixtureBroadcast::class);
    }

    /**
     * @return HasMany<BroadcastSource>
     */
    public function broadcastSources(): HasMany
    {
        return $this->hasMany(BroadcastSource::class);
    }

    /**
     * @return HasMany<FixtureProviderMapping>
     */
    public function providerMappings(): HasMany
    {
        return $this->hasMany(FixtureProviderMapping::class);
    }

    /**
     * @return BelongsToMany<Broadcaster>
     */
    public function broadcasters(): BelongsToMany
    {
        return $this->belongsToMany(Broadcaster::class, 'fixture_broadcasts')
            ->withPivot([
                'broadcast_source_id',
                'access_type',
                'country_code',
                'source_type',
                'source_url',
                'confidence',
                'verified_at',
                'needs_review',
                'notes',
            ])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
            'resolved_at' => 'datetime',
            'resolution_invalidated_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
