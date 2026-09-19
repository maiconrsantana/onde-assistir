<?php

namespace App\Models;

use Database\Factories\FixtureBroadcastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixtureBroadcast extends Model
{
    /** @use HasFactory<FixtureBroadcastFactory> */
    use HasFactory;

    public const ACCESS_FREE = 'free';

    public const ACCESS_SUBSCRIPTION = 'subscription';

    public const ACCESS_PAY_PER_VIEW = 'pay_per_view';

    public const ACCESS_UNKNOWN = 'unknown';

    public const SOURCE_THESPORTSDB = 'sportsdb';

    public const SOURCE_OPENAI = 'openai';

    public const SOURCE_MANUAL = 'manual';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    public const PUBLICATION_DRAFT = 'draft';

    public const PUBLICATION_PUBLISHED = 'published';

    public const PUBLICATION_UNPUBLISHED = 'unpublished';

    protected $guarded = [];

    /**
     * @return BelongsTo<FootballFixture, FixtureBroadcast>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(FootballFixture::class, 'football_fixture_id');
    }

    /**
     * @return BelongsTo<Broadcaster, FixtureBroadcast>
     */
    public function broadcaster(): BelongsTo
    {
        return $this->belongsTo(Broadcaster::class);
    }

    /**
     * @return BelongsTo<BroadcastSource, FixtureBroadcast>
     */
    public function broadcastSource(): BelongsTo
    {
        return $this->belongsTo(BroadcastSource::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'verified_at' => 'datetime',
            'needs_review' => 'boolean',
        ];
    }
}
