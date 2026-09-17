<?php

namespace App\Services\Football;

use App\Data\Football\FixtureSyncResult;
use App\Data\Football\FootballCompetitionData;
use App\Data\Football\FootballFixtureData;
use App\Data\Football\FootballTeamData;
use App\Models\Competition;
use App\Models\FootballFixture;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FixtureSynchronizer
{
    /**
     * @param  iterable<int, FootballFixtureData>  $fixtures
     */
    public function sync(iterable $fixtures): FixtureSyncResult
    {
        $result = new FixtureSyncResult;

        foreach ($fixtures as $fixtureData) {
            try {
                DB::transaction(function () use ($fixtureData, $result): void {
                    $this->assertValidFixture($fixtureData);

                    $competition = $this->syncCompetition($fixtureData->competition, $result);
                    $homeTeam = $this->syncTeam($fixtureData->homeTeam, $result);
                    $awayTeam = $this->syncTeam($fixtureData->awayTeam, $result);

                    $fixture = FootballFixture::updateOrCreate([
                        'provider' => $fixtureData->provider,
                        'external_id' => $fixtureData->externalId,
                    ], [
                        'competition_id' => $competition->id,
                        'home_team_id' => $homeTeam->id,
                        'away_team_id' => $awayTeam->id,
                        'round' => $fixtureData->round,
                        'starts_at' => $fixtureData->startsAt->utc(),
                        'status' => $fixtureData->status,
                        'venue' => $fixtureData->venue,
                        'city' => $fixtureData->city,
                        'source_url' => $fixtureData->sourceUrl,
                        'raw_payload' => $fixtureData->rawPayload,
                        'synced_at' => now()->utc(),
                    ]);

                    $result->recordFixture($fixture->wasRecentlyCreated);
                });
            } catch (Throwable $exception) {
                $message = "Fixture {$fixtureData->provider}:{$fixtureData->externalId} failed: {$exception->getMessage()}";

                $result->recordError($message);

                Log::warning('football.fixture_sync_failed', [
                    'provider' => $fixtureData->provider,
                    'external_id' => $fixtureData->externalId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $result;
    }

    private function assertValidFixture(FootballFixtureData $fixtureData): void
    {
        if (
            $fixtureData->homeTeam->provider === $fixtureData->awayTeam->provider
            && $fixtureData->homeTeam->externalId === $fixtureData->awayTeam->externalId
        ) {
            throw new \InvalidArgumentException('home and away teams must be different');
        }
    }

    private function syncCompetition(FootballCompetitionData $data, FixtureSyncResult $result): Competition
    {
        $competition = Competition::updateOrCreate([
            'provider' => $data->provider,
            'external_id' => $data->externalId,
        ], [
            'name' => $data->name,
            'slug' => $this->uniqueSlug(Competition::class, $data->name, $data->provider, $data->externalId),
            'country_code' => $data->countryCode,
            'season_name' => $data->seasonName,
            'active' => true,
        ]);

        $result->recordCompetition($competition->wasRecentlyCreated);

        return $competition;
    }

    private function syncTeam(FootballTeamData $data, FixtureSyncResult $result): Team
    {
        $team = Team::updateOrCreate([
            'provider' => $data->provider,
            'external_id' => $data->externalId,
        ], [
            'name' => $data->name,
            'short_name' => $data->shortName,
            'slug' => $this->uniqueSlug(Team::class, $data->name, $data->provider, $data->externalId),
            'logo_url' => $data->logoUrl,
        ]);

        $result->recordTeam($team->wasRecentlyCreated);

        return $team;
    }

    /**
     * @param  class-string<Competition|Team>  $modelClass
     */
    private function uniqueSlug(string $modelClass, string $name, string $provider, string $externalId): string
    {
        $baseSlug = Str::slug($name) ?: 'item';

        $existing = $modelClass::query()
            ->where('slug', $baseSlug)
            ->where(function ($query) use ($provider, $externalId): void {
                $query->where('provider', '!=', $provider)
                    ->orWhere('external_id', '!=', $externalId);
            })
            ->exists();

        if (! $existing) {
            return $baseSlug;
        }

        return $baseSlug.'-'.$provider.'-'.$externalId;
    }
}
