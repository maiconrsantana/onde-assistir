<?php

namespace App\Data\Football;

use Carbon\CarbonImmutable;

final readonly class FootballFixtureData
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $provider,
        public string $externalId,
        public FootballCompetitionData $competition,
        public FootballTeamData $homeTeam,
        public FootballTeamData $awayTeam,
        public ?string $round,
        public CarbonImmutable $startsAt,
        public string $status,
        public ?string $venue,
        public ?string $city,
        public ?string $sourceUrl,
        public array $rawPayload,
    ) {}
}
