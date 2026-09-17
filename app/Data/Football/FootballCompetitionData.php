<?php

namespace App\Data\Football;

final readonly class FootballCompetitionData
{
    public function __construct(
        public string $provider,
        public string $externalId,
        public string $name,
        public string $countryCode,
        public string $seasonName,
    ) {}
}
