<?php

namespace Tests\Feature;

use App\Contracts\FootballDataProvider;
use App\Data\Football\FootballCompetitionData;
use App\Data\Football\FootballFixtureData;
use App\Data\Football\FootballTeamData;
use App\Models\Competition;
use App\Models\FootballFixture;
use App\Models\Team;
use App\Services\Football\FixtureSynchronizer;
use App\Services\Operations\PublicScheduleCache;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FixtureSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_sync_creates_competition_teams_and_fixture(): void
    {
        $result = app(FixtureSynchronizer::class)->sync([
            $this->fixtureData(),
        ]);

        $this->assertSame(1, $result->competitionsCreated);
        $this->assertSame(2, $result->teamsCreated);
        $this->assertSame(1, $result->fixturesCreated);
        $this->assertSame(0, $result->errors);

        $this->assertDatabaseHas('competitions', [
            'provider' => 'api_football',
            'external_id' => '71',
            'name' => 'Serie A',
            'country_code' => 'BR',
            'season_name' => '2026',
        ]);

        $this->assertDatabaseHas('teams', [
            'provider' => 'api_football',
            'external_id' => '131',
            'name' => 'Corinthians',
        ]);

        $this->assertDatabaseHas('football_fixtures', [
            'provider' => 'api_football',
            'external_id' => '1200001',
            'round' => 'Regular Season - 1',
            'status' => 'NS',
            'venue' => 'Neo Quimica Arena',
            'city' => 'Sao Paulo',
            'resolution_status' => FootballFixture::RESOLUTION_PENDING,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
        ]);
    }

    public function test_second_sync_with_same_payload_updates_without_duplicates(): void
    {
        $synchronizer = app(FixtureSynchronizer::class);

        $synchronizer->sync([$this->fixtureData()]);
        $result = $synchronizer->sync([$this->fixtureData()]);

        $this->assertSame(0, $result->competitionsCreated);
        $this->assertSame(1, $result->competitionsUpdated);
        $this->assertSame(0, $result->teamsCreated);
        $this->assertSame(2, $result->teamsUpdated);
        $this->assertSame(0, $result->fixturesCreated);
        $this->assertSame(1, $result->fixturesUpdated);

        $this->assertDatabaseCount('competitions', 1);
        $this->assertDatabaseCount('teams', 2);
        $this->assertDatabaseCount('football_fixtures', 1);
    }

    public function test_changed_fixture_time_updates_existing_fixture(): void
    {
        $synchronizer = app(FixtureSynchronizer::class);

        $synchronizer->sync([
            $this->fixtureData(startsAt: CarbonImmutable::parse('2026-09-17T22:30:00+00:00')),
        ]);

        $synchronizer->sync([
            $this->fixtureData(startsAt: CarbonImmutable::parse('2026-09-18T00:00:00+00:00')),
        ]);

        $fixture = FootballFixture::query()->firstOrFail();
        $storedStartsAt = DB::table('football_fixtures')->where('id', $fixture->id)->value('starts_at');

        $this->assertStringContainsString('2026-09-18 00:00:00', (string) $storedStartsAt);
        $this->assertDatabaseHas('football_fixtures', [
            'id' => $fixture->id,
            'starts_at' => '2026-09-18 00:00:00',
        ]);
        $this->assertDatabaseCount('football_fixtures', 1);
    }

    public function test_invalid_fixture_does_not_corrupt_other_fixtures_in_batch(): void
    {
        $result = app(FixtureSynchronizer::class)->sync([
            $this->fixtureData(externalId: 'valid-fixture'),
            $this->fixtureData(
                externalId: 'invalid-fixture',
                homeTeam: new FootballTeamData(
                    provider: 'api_football',
                    externalId: '131',
                    name: 'Corinthians',
                ),
                awayTeam: new FootballTeamData(
                    provider: 'api_football',
                    externalId: '131',
                    name: 'Corinthians',
                ),
            ),
        ]);

        $this->assertSame(1, $result->fixturesCreated);
        $this->assertSame(1, $result->errors);
        $this->assertDatabaseHas('football_fixtures', [
            'external_id' => 'valid-fixture',
        ]);
        $this->assertDatabaseMissing('football_fixtures', [
            'external_id' => 'invalid-fixture',
        ]);
    }

    public function test_slug_collision_gets_provider_suffix_for_new_external_entity(): void
    {
        Competition::factory()->create([
            'provider' => 'manual',
            'external_id' => 'serie-a',
            'name' => 'Serie A',
            'slug' => 'serie-a',
        ]);

        Team::factory()->create([
            'provider' => 'manual',
            'external_id' => 'corinthians',
            'name' => 'Corinthians',
            'slug' => 'corinthians',
        ]);

        app(FixtureSynchronizer::class)->sync([
            $this->fixtureData(),
        ]);

        $this->assertDatabaseHas('competitions', [
            'provider' => 'api_football',
            'external_id' => '71',
            'slug' => 'serie-a-api_football-71',
        ]);

        $this->assertDatabaseHas('teams', [
            'provider' => 'api_football',
            'external_id' => '131',
            'slug' => 'corinthians-api_football-131',
        ]);
    }

    public function test_sync_command_fetches_provider_and_persists_fixtures(): void
    {
        $this->app->bind(FootballDataProvider::class, fn () => new class($this->fixtureData()) implements FootballDataProvider
        {
            public function __construct(private readonly FootballFixtureData $fixture) {}

            public function fixturesBetween(CarbonInterface $from, CarbonInterface $to): array
            {
                return [$this->fixture];
            }
        });

        $this->artisan('football:sync', [
            '--from' => '2026-09-17',
            '--to' => '2026-09-24',
        ])
            ->assertExitCode(0);

        $this->assertDatabaseHas('football_fixtures', [
            'provider' => 'api_football',
            'external_id' => '1200001',
        ]);
    }

    public function test_provider_sync_publishes_through_web_and_api_boundaries(): void
    {
        $this->app->bind(FootballDataProvider::class, fn () => new class($this->fixtureData(startsAt: CarbonImmutable::parse('2026-09-20T22:30:00+00:00'))) implements FootballDataProvider
        {
            public function __construct(private readonly FootballFixtureData $fixture) {}

            public function fixturesBetween(CarbonInterface $from, CarbonInterface $to): array
            {
                return [$this->fixture];
            }
        });

        $this->artisan('football:sync', [
            '--from' => '2026-09-19',
            '--to' => '2026-09-20',
        ])->assertExitCode(0);

        $fixture = FootballFixture::query()->firstOrFail();
        $fixture->update([
            'review_status' => FootballFixture::REVIEW_APPROVED,
            'publication_status' => FootballFixture::PUBLICATION_PUBLISHED,
        ]);
        app(PublicScheduleCache::class)->invalidate();

        $this->get('/')->assertOk()->assertSeeText('Corinthians')->assertSeeText('Palmeiras');
        $this->getJson('/api/v1/fixtures')
            ->assertOk()
            ->assertJsonPath('data.0.external_id', '1200001');
    }

    private function fixtureData(
        string $externalId = '1200001',
        ?CarbonImmutable $startsAt = null,
        ?FootballTeamData $homeTeam = null,
        ?FootballTeamData $awayTeam = null,
    ): FootballFixtureData {
        return new FootballFixtureData(
            provider: 'api_football',
            externalId: $externalId,
            competition: new FootballCompetitionData(
                provider: 'api_football',
                externalId: '71',
                name: 'Serie A',
                countryCode: 'BR',
                seasonName: '2026',
            ),
            homeTeam: $homeTeam ?? new FootballTeamData(
                provider: 'api_football',
                externalId: '131',
                name: 'Corinthians',
                logoUrl: 'https://example.com/corinthians.png',
            ),
            awayTeam: $awayTeam ?? new FootballTeamData(
                provider: 'api_football',
                externalId: '121',
                name: 'Palmeiras',
                logoUrl: 'https://example.com/palmeiras.png',
            ),
            round: 'Regular Season - 1',
            startsAt: $startsAt ?? CarbonImmutable::parse('2026-09-17T22:30:00+00:00'),
            status: 'NS',
            venue: 'Neo Quimica Arena',
            city: 'Sao Paulo',
            sourceUrl: null,
            rawPayload: [
                'fixture' => [
                    'id' => $externalId,
                ],
            ],
        );
    }
}
