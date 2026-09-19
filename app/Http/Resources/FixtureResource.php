<?php

namespace App\Http\Resources;

use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Support\ExternalUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FootballFixture */
class FixtureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'starts_at' => $this->starts_at?->utc()->toIso8601String(),
            'timezone' => 'America/Sao_Paulo',
            'competition' => [
                'id' => $this->competition?->id,
                'name' => $this->competition?->name,
                'season' => $this->competition?->season_name,
            ],
            'round' => $this->round,
            'home_team' => [
                'id' => $this->homeTeam?->id,
                'name' => $this->homeTeam?->name,
                'short_name' => $this->homeTeam?->short_name,
            ],
            'away_team' => [
                'id' => $this->awayTeam?->id,
                'name' => $this->awayTeam?->name,
                'short_name' => $this->awayTeam?->short_name,
            ],
            'venue' => $this->venue,
            'city' => $this->city,
            'broadcasts' => $this->fixtureBroadcasts->map(
                fn (FixtureBroadcast $broadcast): array => [
                    'id' => $broadcast->id,
                    'broadcaster' => [
                        'id' => $broadcast->broadcaster?->id,
                        'name' => $broadcast->broadcaster?->name,
                        'type' => $broadcast->broadcaster?->type,
                    ],
                    'access_type' => $broadcast->access_type,
                    'source_url' => ExternalUrl::normalize($broadcast->source_url),
                ]
            )->values(),
        ];
    }
}
