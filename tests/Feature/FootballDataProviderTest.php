<?php

namespace Tests\Feature;

use App\Contracts\FootballDataProvider;
use App\Exceptions\FootballDataProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FootballDataProviderTest extends TestCase
{
    public function test_provider_fetches_and_maps_brazilian_fixtures(): void
    {
        $this->configureProvider();
        Http::fake(['https://api.football-data.org/v4/competitions/*' => Http::response($this->fixture())]);

        $fixtures = app(FootballDataProvider::class)->fixturesBetween(
            CarbonImmutable::parse('2026-09-17', 'America/Sao_Paulo'),
            CarbonImmutable::parse('2026-09-24', 'America/Sao_Paulo'),
        );

        $this->assertCount(1, $fixtures);
        $this->assertSame('football_data', $fixtures[0]->provider);
        $this->assertSame('4500001', $fixtures[0]->externalId);
        $this->assertSame('BR', $fixtures[0]->competition->countryCode);
        $this->assertSame('2026', $fixtures[0]->competition->seasonName);
        $this->assertSame('Rodada 25', $fixtures[0]->round);
        $this->assertSame('2026-09-20 22:30:00', $fixtures[0]->startsAt->toDateTimeString());
        $this->assertSame('Corinthians', $fixtures[0]->homeTeam->name);
        $this->assertSame('Palmeiras', $fixtures[0]->awayTeam->name);

        Http::assertSent(fn ($request): bool => $request->hasHeader('X-Auth-Token', 'test-token')
            && str_contains($request->url(), 'competitions/BSA/matches')
            && str_contains($request->url(), 'dateFrom=2026-09-17')
            && str_contains($request->url(), 'dateTo=2026-09-24')
            && str_contains($request->url(), 'season=2026'));
    }

    public function test_provider_returns_empty_array_without_matches(): void
    {
        $this->configureProvider();
        Http::fake(['https://api.football-data.org/v4/competitions/*' => Http::response(['matches' => []])]);

        $fixtures = app(FootballDataProvider::class)->fixturesBetween(
            CarbonImmutable::parse('2026-09-17'),
            CarbonImmutable::parse('2026-09-24'),
        );

        $this->assertSame([], $fixtures);
    }

    public function test_provider_rejects_api_errors(): void
    {
        $this->configureProvider();
        Http::fake(['https://api.football-data.org/v4/competitions/*' => Http::response(['message' => 'Restricted'], 403)]);

        $this->expectException(FootballDataProviderException::class);
        $this->expectExceptionMessage('HTTP 403');

        app(FootballDataProvider::class)->fixturesBetween(
            CarbonImmutable::parse('2026-09-17'),
            CarbonImmutable::parse('2026-09-24'),
        );
    }

    public function test_provider_rejects_invalid_payload(): void
    {
        $this->configureProvider();
        Http::fake(['https://api.football-data.org/v4/competitions/*' => Http::response(['resultSet' => []])]);

        $this->expectException(FootballDataProviderException::class);
        $this->expectExceptionMessage('missing matches array');

        app(FootballDataProvider::class)->fixturesBetween(
            CarbonImmutable::parse('2026-09-17'),
            CarbonImmutable::parse('2026-09-24'),
        );
    }

    private function configureProvider(): void
    {
        config([
            'services.football_data.base_url' => 'https://api.football-data.org/v4',
            'services.football_data.token' => 'test-token',
            'services.football_data.competition' => 'BSA',
            'services.football_data.season' => '2026',
            'services.football_data.timeout' => 15,
            'services.football_data.retry_times' => 0,
            'services.football_data.retry_sleep' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/football-data/matches.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
