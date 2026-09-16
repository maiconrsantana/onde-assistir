<?php

namespace App\Models;

use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<FootballFixture>
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(FootballFixture::class);
    }

    /**
     * @return HasMany<RoundPublication>
     */
    public function roundPublications(): HasMany
    {
        return $this->hasMany(RoundPublication::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
