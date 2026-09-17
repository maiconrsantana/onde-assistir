<?php

namespace App\Data\Football;

final class FixtureSyncResult
{
    public int $competitionsCreated = 0;

    public int $competitionsUpdated = 0;

    public int $teamsCreated = 0;

    public int $teamsUpdated = 0;

    public int $fixturesCreated = 0;

    public int $fixturesUpdated = 0;

    public int $errors = 0;

    /**
     * @var array<int, string>
     */
    public array $errorMessages = [];

    public function recordCompetition(bool $created): void
    {
        $created ? $this->competitionsCreated++ : $this->competitionsUpdated++;
    }

    public function recordTeam(bool $created): void
    {
        $created ? $this->teamsCreated++ : $this->teamsUpdated++;
    }

    public function recordFixture(bool $created): void
    {
        $created ? $this->fixturesCreated++ : $this->fixturesUpdated++;
    }

    public function recordError(string $message): void
    {
        $this->errors++;
        $this->errorMessages[] = $message;
    }

    /**
     * @return array<string, int>
     */
    public function totals(): array
    {
        return [
            'competitions_created' => $this->competitionsCreated,
            'competitions_updated' => $this->competitionsUpdated,
            'teams_created' => $this->teamsCreated,
            'teams_updated' => $this->teamsUpdated,
            'fixtures_created' => $this->fixturesCreated,
            'fixtures_updated' => $this->fixturesUpdated,
            'errors' => $this->errors,
        ];
    }
}
