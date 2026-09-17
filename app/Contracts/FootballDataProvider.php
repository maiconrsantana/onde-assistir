<?php

namespace App\Contracts;

use App\Data\Football\FootballFixtureData;
use Carbon\CarbonInterface;

interface FootballDataProvider
{
    /**
     * Fetch normalized football fixtures between two dates.
     *
     * @return array<int, FootballFixtureData>
     */
    public function fixturesBetween(CarbonInterface $from, CarbonInterface $to): array;
}
