<?php

namespace App\Integrations\FootballData;

use App\Contracts\FootballDataProvider as FootballDataProviderContract;
use App\Data\Football\FootballCompetitionData;
use App\Data\Football\FootballFixtureData;
use App\Data\Football\FootballTeamData;
use App\Exceptions\FootballDataProviderException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FootballDataProvider implements FootballDataProviderContract
{
    private const PROVIDER = 'football_data';

    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $token,
        private readonly ?string $competition,
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

        try {
            $response = Http::baseUrl(rtrim((string) $this->baseUrl, '/'))
                ->acceptJson()
                ->withHeaders(['X-Auth-Token' => $this->token])
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep, throw: false)
                ->get("competitions/{$this->competition}/matches", [
                    'dateFrom' => CarbonImmutable::instance($from)->toDateString(),
                    'dateTo' => CarbonImmutable::instance($to)->toDateString(),
                    'season' => $this->season,
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

        if (! array_key_exists('matches', $payload) || ! is_array($payload['matches'])) {
            throw FootballDataProviderException::invalidPayload('missing matches array');
        }

        return array_map(fn (array $match): FootballFixtureData => $this->mapFixture($match), $payload['matches']);
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function mapFixture(array $match): FootballFixtureData
    {
        $matchId = data_get($match, 'id');
        $competitionId = data_get($match, 'competition.id');
        $homeTeamId = data_get($match, 'homeTeam.id');
        $awayTeamId = data_get($match, 'awayTeam.id');
        $date = data_get($match, 'utcDate');

        foreach ([
            'id' => $matchId,
            'competition.id' => $competitionId,
            'homeTeam.id' => $homeTeamId,
            'awayTeam.id' => $awayTeamId,
            'utcDate' => $date,
        ] as $path => $value) {
            if ($value === null || $value === '') {
                throw FootballDataProviderException::invalidPayload("missing {$path}");
            }
        }

        $seasonName = (string) data_get($match, 'season.startDate', $this->season);

        return new FootballFixtureData(
            provider: self::PROVIDER,
            externalId: (string) $matchId,
            competition: new FootballCompetitionData(
                provider: self::PROVIDER,
                externalId: (string) $competitionId,
                name: (string) data_get($match, 'competition.name', 'Unknown competition'),
                countryCode: $this->countryCodeFromName((string) data_get($match, 'area.name', 'Brazil')),
                seasonName: Str::substr($seasonName, 0, 4),
            ),
            homeTeam: new FootballTeamData(
                provider: self::PROVIDER,
                externalId: (string) $homeTeamId,
                name: (string) data_get($match, 'homeTeam.name', 'Unknown home team'),
                shortName: data_get($match, 'homeTeam.shortName'),
                logoUrl: data_get($match, 'homeTeam.crest'),
            ),
            awayTeam: new FootballTeamData(
                provider: self::PROVIDER,
                externalId: (string) $awayTeamId,
                name: (string) data_get($match, 'awayTeam.name', 'Unknown away team'),
                shortName: data_get($match, 'awayTeam.shortName'),
                logoUrl: data_get($match, 'awayTeam.crest'),
            ),
            round: data_get($match, 'matchday') !== null ? 'Rodada '.data_get($match, 'matchday') : data_get($match, 'stage'),
            startsAt: CarbonImmutable::parse($date)->utc(),
            status: (string) data_get($match, 'status', 'UNKNOWN'),
            venue: data_get($match, 'venue'),
            city: null,
            sourceUrl: null,
            rawPayload: $match,
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

        return (string) ($payload['message'] ?? '');
    }

    private function ensureConfigured(): void
    {
        foreach ([
            'services.football_data.base_url' => $this->baseUrl,
            'services.football_data.token' => $this->token,
            'services.football_data.competition' => $this->competition,
            'services.football_data.season' => $this->season,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                throw FootballDataProviderException::missingConfiguration($key);
            }
        }
    }
}
