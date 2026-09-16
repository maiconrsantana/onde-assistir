<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<FootballFixture>
     */
    public function homeFixtures(): HasMany
    {
        return $this->hasMany(FootballFixture::class, 'home_team_id');
    }

    /**
     * @return HasMany<FootballFixture>
     */
    public function awayFixtures(): HasMany
    {
        return $this->hasMany(FootballFixture::class, 'away_team_id');
    }
}
