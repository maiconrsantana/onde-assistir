<?php

namespace Tests\Feature;

use App\Contracts\FootballDataProvider;
use App\Exceptions\FootballDataProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use JsonException;
use Tests\TestCase;

class ApiFootballProviderTest extends TestCase
{
    public function test_provider_fetches_fixtures_with_pagination_and_maps_payload(): void
    {
        $this->configureApiFootball();

        Http::fake([
            'https://v3.football.api-sports.io/fixtures*' => Http::sequence()
                ->push($this->fixture('fixtures_page_1.json'))
                ->push($this->fixture('fixtures_page_2.json')),
        ]);

        $fixtures = app(FootballDataProvider::class)->fixturesBetween(
            CarbonImmutable::parse('2026-09-17', 'America/Sao_Paulo'),
            CarbonImmutable::parse('2026-09-24', 'America/Sao_Paulo'),
        );

        $this->assertCount(2, $fixtures);
        $this->assertSame('api_football', $fixtures[0]->provider);
        $this->assertSame('1200001', $fixtures[0]->externalId);
        $this->assertSame('Serie A', $fixtures[0]->competition->name);
        $this->assertSame('BR', $fixtures[0]->competition->countryCode);
        $this->assertSame('2026', $fixtures[0]->competition->seasonName);
        $this->assertSame('Corinthians', $fixtures[0]->homeTeam->name);
        $this->assertSame('Palmeiras', $fixtures[0]->awayTeam->name);
        $this->assertSame('Regular Season - 1', $fixtures[0]->round);
        $this->assertSame('2026-09-17 22:30:00', $fixtures[0]->startsAt->toDateTimeString());
        $this->assertSame('NS', $fixtures[0]->status);
        $this->assertSame('Neo Quimica Arena', $fixtures[0]->venue);
        $this->assertSame('Sao Paulo', $fixtures[0]->city);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->hasHeader('x-apisports-key', 'test-key')
            && str_contains($request->url(), 'league=71')
            && str_contains($request->url(), 'season=2026')
            && str_contains($request->url(), 'timezone=UTC'));
    }

    public function test_provider_returns_empty_array_when_api_has_no_fixtures(): void
    {
        $this->configureApiFootball();

        Http::fake([
            'https://v3.football.api-sports.io/fixtures*' => Http::response($this->fixture('fixtures_empty.json')),
        ]);

        $fixtures = app(FootballDataProvider::class)->fixturesBetween(
            CarbonImmutable::parse('2026-09-17'),
            CarbonImmutable::parse('2026-09-24'),
        );

        $this->assertSame([], $fixtures);
    }

    public function test_provider_rejects_api_errors_payload(): void
    {
        $this->configureApiFootball();

        Http::fake([
            'https://v3.football.api-sports.io/fixtures*' => Http::response($this->fixture('fixtures_error.json')),
        ]);

        $this->expectException(FootballDataProviderException::class);
        $this->expectExceptionMessage('league');

        app(FootballDataProvider::class)->fixturesBetween(
            CarbonImmutable::parse('2026-09-17'),
            CarbonImmutable::parse('2026-09-24'),
        );
    }

    public function test_provider_rejects_rate_limit_response(): void
    {
        $this->configureApiFootball();

        Http::fake([
            'https://v3.football.api-sports.io/fixtures*' => Http::response([
                'message' => 'Too many requests',
            ], 429),
        ]);

        $this->expectException(FootballDataProviderException::class);
        $this->expectExceptionMessage('HTTP 429');

        app(FootballDataProvider::class)->fixturesBetween(
            CarbonImmutable::parse('2026-09-17'),
            CarbonImmutable::parse('2026-09-24'),
        );
    }

    public function test_provider_rejects_invalid_payload(): void
    {
        $this->configureApiFootball();

        Http::fake([
            'https://v3.football.api-sports.io/fixtures*' => Http::response([
                'errors' => [],
                'paging' => ['current' => 1, 'total' => 1],
            ]),
        ]);

        $this->expectException(FootballDataProviderException::class);
        $this->expectExceptionMessage('missing response array');

        app(FootballDataProvider::class)->fixturesBetween(
            CarbonImmutable::parse('2026-09-17'),
            CarbonImmutable::parse('2026-09-24'),
        );
    }

    public function test_probe_command_outputs_normalized_json_without_persisting_data(): void
    {
        $this->configureApiFootball();

        Http::fake([
            'https://v3.football.api-sports.io/fixtures*' => Http::response($this->fixture('fixtures_empty.json')),
        ]);

        $this->artisan('football:probe-provider', [
            '--from' => '2026-09-17',
            '--to' => '2026-09-24',
            '--json' => true,
        ])
            ->expectsOutputToContain('[]')
            ->assertExitCode(0);
    }

    private function configureApiFootball(): void
    {
        config([
            'services.api_football.base_url' => 'https://v3.football.api-sports.io',
            'services.api_football.key' => 'test-key',
            'services.api_football.brasileirao_league_id' => '71',
            'services.api_football.season' => '2026',
            'services.api_football.timeout' => 15,
            'services.api_football.retry_times' => 0,
            'services.api_football.retry_sleep' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function fixture(string $filename): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/api-football/{$filename}")),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
