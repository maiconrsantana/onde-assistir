<?php

namespace App\Data\Football;

final readonly class FootballTeamData
{
    public function __construct(
        public string $provider,
        public string $externalId,
        public string $name,
        public ?string $shortName = null,
        public ?string $logoUrl = null,
    ) {}
}
