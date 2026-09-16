<?php

namespace App\Models;

use Database\Factories\RoundPublicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundPublication extends Model
{
    /** @use HasFactory<RoundPublicationFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_UNPUBLISHED = 'unpublished';

    protected $guarded = [];

    /**
     * @return BelongsTo<Competition, RoundPublication>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<User, RoundPublication>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'audit_payload' => 'array',
        ];
    }
}
