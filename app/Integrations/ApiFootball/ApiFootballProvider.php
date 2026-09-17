<?php

namespace App\Integrations\ApiFootball;

use App\Contracts\FootballDataProvider;
use App\Data\Football\FootballCompetitionData;
use App\Data\Football\FootballFixtureData;
use App\Data\Football\FootballTeamData;
use App\Exceptions\FootballDataProviderException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ApiFootballProvider implements FootballDataProvider
{
    private const PROVIDER = 'api_football';

    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $key,
        private readonly ?string $leagueId,
        private readonly ?string $season,
        private readonly int $timeout = 15,
        private readonly int $retryTimes = 2,
        private readonly int $retrySleep = 500,
    ) {}

    /**
     * @return array<int, FootballFixtureData>
     */
    public function fixturesBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $this->ensureConfigured();

        $page = 1;
        $fixtures = [];

        do {
            $payload = $this->getFixturesPayload($from, $to, $page);

            foreach ($payload['response'] as $item) {
                $fixtures[] = $this->mapFixture($item);
            }

            $currentPage = (int) data_get($payload, 'paging.current', $page);
            $totalPages = (int) data_get($payload, 'paging.total', $currentPage);
            $page++;
        } while ($currentPage < $totalPages);

        return $fixtures;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFixturesPayload(CarbonInterface $from, CarbonInterface $to, int $page): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) $this->baseUrl, '/'))
                ->acceptJson()
                ->withHeaders([
                    'x-apisports-key' => $this->key,
                ])
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep, throw: false)
                ->get('fixtures', [
                    'league' => $this->leagueId,
                    'season' => $this->season,
                    'from' => CarbonImmutable::instance($from)->utc()->toDateString(),
                    'to' => CarbonImmutable::instance($to)->utc()->toDateString(),
                    'timezone' => 'UTC',
                    'page' => $page,
                ]);
        } catch (ConnectionException $exception) {
            throw FootballDataProviderException::requestFailed(0, $exception->getMessage());
        }

        if (! $response->successful()) {
            throw FootballDataProviderException::requestFailed($response->status(), $this->responseErrorMessage($response->json()));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw FootballDataProviderException::invalidPayload('response body is not a JSON object');
        }

        $this->assertValidPayload($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertValidPayload(array $payload): void
    {
        $errors = $payload['errors'] ?? [];

        if (is_array($errors) && $errors !== []) {
            throw FootballDataProviderException::invalidPayload($this->responseErrorMessage($payload));
        }

        if (! array_key_exists('response', $payload) || ! is_array($payload['response'])) {
            throw FootballDataProviderException::invalidPayload('missing response array');
        }

        if (! array_key_exists('paging', $payload) || ! is_array($payload['paging'])) {
            throw FootballDataProviderException::invalidPayload('missing paging object');
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapFixture(array $item): FootballFixtureData
    {
        $fixtureId = data_get($item, 'fixture.id');
        $leagueId = data_get($item, 'league.id');
        $homeTeamId = data_get($item, 'teams.home.id');
        $awayTeamId = data_get($item, 'teams.away.id');
        $date = data_get($item, 'fixture.date');

        foreach ([
            'fixture.id' => $fixtureId,
            'league.id' => $leagueId,
            'teams.home.id' => $homeTeamId,
            'teams.away.id' => $awayTeamId,
            'fixture.date' => $date,
        ] as $path => $value) {
            if ($value === null || $value === '') {
                throw FootballDataProviderException::invalidPayload("missing {$path}");
            }
        }

        return new FootballFixtureData(
            provider: self::PROVIDER,
            externalId: (string) $fixtureId,
            competition: new FootballCompetitionData(
                provider: self::PROVIDER,
                externalId: (string) $leagueId,
                name: (string) data_get($item, 'league.name', 'Unknown competition'),
                countryCode: $this->countryCodeFromName((string) data_get($item, 'league.country', '')),
                seasonName: (string) data_get($item, 'league.season', $this->season),
            ),
            homeTeam: new FootballTeamData(
                provider: self::PROVIDER,
                externalId: (string) $homeTeamId,
                name: (string) data_get($item, 'teams.home.name', 'Unknown home team'),
                logoUrl: data_get($item, 'teams.home.logo'),
            ),
            awayTeam: new FootballTeamData(
                provider: self::PROVIDER,
                externalId: (string) $awayTeamId,
                name: (string) data_get($item, 'teams.away.name', 'Unknown away team'),
                logoUrl: data_get($item, 'teams.away.logo'),
            ),
            round: data_get($item, 'league.round'),
            startsAt: CarbonImmutable::parse($date)->utc(),
            status: (string) data_get($item, 'fixture.status.short', 'unknown'),
            venue: data_get($item, 'fixture.venue.name'),
            city: data_get($item, 'fixture.venue.city'),
            sourceUrl: null,
            rawPayload: $item,
        );
    }

    private function countryCodeFromName(string $country): string
    {
        return match (Str::lower($country)) {
            'brazil', 'brasil' => 'BR',
            default => Str::upper(Str::substr($country, 0, 2)) ?: 'XX',
        };
    }

    private function responseErrorMessage(mixed $payload): string
    {
        if (! is_array($payload)) {
            return '';
        }

        $errors = $payload['errors'] ?? null;

        if (is_string($errors)) {
            return $errors;
        }

        if (is_array($errors)) {
            try {
                return (string) json_encode($errors, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return 'unreadable API errors object';
            }
        }

        return '';
    }

    private function ensureConfigured(): void
    {
        foreach ([
            'services.api_football.base_url' => $this->baseUrl,
            'services.api_football.key' => $this->key,
            'services.api_football.brasileirao_league_id' => $this->leagueId,
            'services.api_football.season' => $this->season,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                throw FootballDataProviderException::missingConfiguration($key);
            }
        }
    }
}
