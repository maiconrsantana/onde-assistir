<?php

namespace App\Models;

use Database\Factories\BroadcasterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcaster extends Model
{
    /** @use HasFactory<BroadcasterFactory> */
    use HasFactory;

    public const TYPE_TV_OPEN = 'tv_open';

    public const TYPE_TV_CLOSED = 'tv_closed';

    public const TYPE_STREAMING = 'streaming';

    public const TYPE_YOUTUBE = 'youtube';

    public const TYPE_OTHER = 'other';

    protected $guarded = [];

    /**
     * @return HasMany<FixtureBroadcast>
     */
    public function fixtureBroadcasts(): HasMany
    {
        return $this->hasMany(FixtureBroadcast::class);
    }

    /**
     * @return BelongsToMany<FootballFixture>
     */
    public function fixtures(): BelongsToMany
    {
        return $this->belongsToMany(FootballFixture::class, 'fixture_broadcasts')
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
}
