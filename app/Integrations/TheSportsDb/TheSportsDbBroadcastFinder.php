<?php

namespace App\Integrations\TheSportsDb;

use App\Contracts\BroadcastFinder;
use App\Data\Broadcast\BroadcastChannelData;
use App\Data\Broadcast\BroadcastSearchResult;
use App\Exceptions\BroadcastFinderException;
use App\Models\Broadcaster;
use App\Models\BroadcastSource;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class TheSportsDbBroadcastFinder implements BroadcastFinder
{
    private const PROVIDER = BroadcastSource::PROVIDER_THESPORTSDB;

    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $key,
        private readonly int $timeout = 15,
        private readonly int $retryTimes = 2,
        private readonly int $retrySleep = 500,
        private readonly string $country = 'Brazil',
    ) {}

    public function findForFixture(FootballFixture $fixture): BroadcastSearchResult
    {
        $this->ensureConfigured();

        $fixture->loadMissing(['competition', 'homeTeam', 'awayTeam']);

        $queryHash = $this->queryHash($fixture);
        $eventSearchPayload = $this->searchEvent($fixture);
        $event = $this->bestEventForFixture($fixture, $this->eventsFromPayload($eventSearchPayload));

        if ($event === null) {
            return new BroadcastSearchResult(
                provider: self::PROVIDER,
                status: BroadcastSource::RESULT_NOT_FOUND,
                queryHash: $queryHash,
                evidenceSummary: 'TheSportsDB nao retornou evento correspondente para a partida.',
                rawResponse: [
                    'event_search' => $eventSearchPayload,
                ],
                calculatedConfidence: 0.0,
            );
        }

        $externalEventId = (string) data_get($event, 'idEvent');
        $matchScore = $this->matchScore($fixture, $event);

        if ($externalEventId === '' || $matchScore < 0.8) {
            return new BroadcastSearchResult(
                provider: self::PROVIDER,
                status: BroadcastSource::RESULT_UNCERTAIN,
                queryHash: $queryHash,
                evidenceSummary: 'TheSportsDB retornou evento, mas a associacao com a partida local ficou incerta.',
                rawResponse: [
                    'event_search' => $eventSearchPayload,
                    'matched_event' => $event,
                ],
                externalEventId: $externalEventId !== '' ? $externalEventId : null,
                calculatedConfidence: $matchScore,
                matchScore: $matchScore,
            );
        }

        $tvPayload = $this->lookupTv($externalEventId);
        $channels = $this->channelsFromPayload($tvPayload);

        if ($channels === []) {
            return new BroadcastSearchResult(
                provider: self::PROVIDER,
                status: BroadcastSource::RESULT_NOT_FOUND,
                queryHash: $queryHash,
                evidenceSummary: 'TheSportsDB encontrou o evento, mas nao retornou transmissao utilizavel no Brasil.',
                rawResponse: [
                    'event_search' => $eventSearchPayload,
                    'matched_event' => $event,
                    'tv_lookup' => $tvPayload,
                ],
                externalEventId: $externalEventId,
                calculatedConfidence: $matchScore,
                matchScore: $matchScore,
            );
        }

        $eventUrl = "https://www.thesportsdb.com/event/{$externalEventId}";

        return new BroadcastSearchResult(
            provider: self::PROVIDER,
            status: BroadcastSource::RESULT_FOUND,
            queryHash: $queryHash,
            channels: $channels,
            evidence: [[
                'url' => $eventUrl,
                'publisher' => 'TheSportsDB',
                'summary' => 'Agenda de TV retornada para o evento correspondente.',
            ]],
            evidenceSummary: 'TheSportsDB retornou transmissao para o evento correspondente no Brasil.',
            rawResponse: [
                'event_search' => $eventSearchPayload,
                'matched_event' => $event,
                'tv_lookup' => $tvPayload,
            ],
            externalEventId: $externalEventId,
            providerConfidence: $matchScore,
            calculatedConfidence: min(1.0, max(0.85, $matchScore)),
            matchScore: $matchScore,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function searchEvent(FootballFixture $fixture): array
    {
        return $this->get('searchevents.php', [
            'e' => $fixture->homeTeam->name.'_vs_'.$fixture->awayTeam->name,
            'd' => CarbonImmutable::parse($fixture->starts_at)->setTimezone(config('app.timezone'))->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupTv(string $externalEventId): array
    {
        return $this->get('lookuptv.php', [
            'id' => $externalEventId,
        ]);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query): array
    {
        try {
            $response = Http::baseUrl($this->v1BaseUrl())
                ->acceptJson()
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep, throw: false)
                ->get($endpoint, $query);
        } catch (ConnectionException $exception) {
            throw BroadcastFinderException::requestFailed('TheSportsDB', 0, $exception->getMessage());
        }

        if (! $response->successful()) {
            throw BroadcastFinderException::requestFailed('TheSportsDB', $response->status(), $this->responseErrorMessage($response->json()));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw BroadcastFinderException::invalidPayload('TheSportsDB', 'response body is not a JSON object');
        }

        return $payload;
    }

    private function v1BaseUrl(): string
    {
        return rtrim((string) $this->baseUrl, '/').'/'.trim((string) $this->key, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function eventsFromPayload(array $payload): array
    {
        $events = $payload['event'] ?? $payload['events'] ?? [];

        return is_array($events) ? array_values(array_filter($events, 'is_array')) : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>|null
     */
    private function bestEventForFixture(FootballFixture $fixture, array $events): ?array
    {
        $bestEvent = null;
        $bestScore = 0.0;

        foreach ($events as $event) {
            $score = $this->matchScore($fixture, $event);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestEvent = $event;
            }
        }

        return $bestEvent;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function matchScore(FootballFixture $fixture, array $event): float
    {
        $homeName = $this->normalizeName($fixture->homeTeam->name);
        $awayName = $this->normalizeName($fixture->awayTeam->name);
        $eventHomeName = $this->normalizeName((string) data_get($event, 'strHomeTeam', ''));
        $eventAwayName = $this->normalizeName((string) data_get($event, 'strAwayTeam', ''));
        $eventName = $this->normalizeName((string) data_get($event, 'strEvent', ''));

        $score = 0.0;

        if ($homeName !== '' && $eventHomeName !== '' && $this->namesMatch($homeName, $eventHomeName)) {
            $score += 0.35;
        }

        if ($awayName !== '' && $eventAwayName !== '' && $this->namesMatch($awayName, $eventAwayName)) {
            $score += 0.35;
        }

        if (
            $score === 0.0
            && $homeName !== ''
            && $awayName !== ''
            && Str::contains($eventName, $homeName)
            && Str::contains($eventName, $awayName)
        ) {
            $score += 0.45;
        }

        $fixtureDate = CarbonImmutable::parse($fixture->starts_at)->setTimezone(config('app.timezone'))->toDateString();
        $eventDate = (string) data_get($event, 'dateEvent', data_get($event, 'strDate', ''));

        if ($eventDate === $fixtureDate) {
            $score += 0.2;
        }

        $competitionName = $this->normalizeName($fixture->competition->name);
        $leagueName = $this->normalizeName((string) data_get($event, 'strLeague', ''));

        if ($competitionName !== '' && $leagueName !== '' && $this->namesMatch($competitionName, $leagueName)) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    private function namesMatch(string $expected, string $actual): bool
    {
        return $expected === $actual || Str::contains($actual, $expected) || Str::contains($expected, $actual);
    }

    private function normalizeName(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, BroadcastChannelData>
     */
    private function channelsFromPayload(array $payload): array
    {
        $rows = $payload['tvevent'] ?? $payload['tv'] ?? $payload['eventtv'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->filter(fn (array $row): bool => $this->isBrazilianTvRow($row))
            ->map(fn (array $row): ?BroadcastChannelData => $this->channelFromRow($row))
            ->filter()
            ->unique(fn (BroadcastChannelData $channel): string => Str::lower($channel->name))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isBrazilianTvRow(array $row): bool
    {
        $country = $this->normalizeName((string) data_get($row, 'strCountry', data_get($row, 'country', '')));

        return $country === '' || in_array($country, ['brazil', 'brasil'], true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function channelFromRow(array $row): ?BroadcastChannelData
    {
        $name = trim((string) data_get($row, 'strChannel', data_get($row, 'channel', data_get($row, 'strChannelName', ''))));

        if ($name === '') {
            return null;
        }

        $sourceUrl = data_get($row, 'strWebsite', data_get($row, 'strUrl'));

        return new BroadcastChannelData(
            name: $name,
            type: $this->channelType($name),
            accessType: $this->accessType($name),
            sourceUrl: is_string($sourceUrl) && Str::startsWith($sourceUrl, ['http://', 'https://']) ? $sourceUrl : null,
        );
    }

    private function channelType(string $name): string
    {
        $normalized = $this->normalizeName($name);

        if (Str::contains($normalized, ['youtube'])) {
            return Broadcaster::TYPE_YOUTUBE;
        }

        if (Str::contains($normalized, ['prime video', 'disney', 'paramount', 'max', 'netflix', 'cazetv', 'caze tv'])) {
            return Broadcaster::TYPE_STREAMING;
        }

        if (in_array($normalized, ['globo', 'sbt', 'record', 'band', 'cultura'], true)) {
            return Broadcaster::TYPE_TV_OPEN;
        }

        if (Str::contains($normalized, ['sportv', 'espn', 'tnt', 'premiere', 'bandsports'])) {
            return Broadcaster::TYPE_TV_CLOSED;
        }

        return Broadcaster::TYPE_OTHER;
    }

    private function accessType(string $name): string
    {
        $normalized = $this->normalizeName($name);

        if (Str::contains($normalized, ['premiere', 'pay per view', 'ppv'])) {
            return FixtureBroadcast::ACCESS_PAY_PER_VIEW;
        }

        if (Str::contains($normalized, ['sportv', 'espn', 'tnt', 'prime video', 'disney', 'paramount', 'max'])) {
            return FixtureBroadcast::ACCESS_SUBSCRIPTION;
        }

        if (in_array($normalized, ['globo', 'sbt', 'record', 'band', 'cultura', 'youtube'], true)) {
            return FixtureBroadcast::ACCESS_FREE;
        }

        return FixtureBroadcast::ACCESS_UNKNOWN;
    }

    private function queryHash(FootballFixture $fixture): string
    {
        return hash('sha256', implode('|', [
            self::PROVIDER,
            $fixture->id,
            $fixture->competition->name,
            $fixture->competition->season_name,
            $fixture->round,
            $fixture->homeTeam->name,
            $fixture->awayTeam->name,
            CarbonImmutable::parse($fixture->starts_at)->utc()->toIso8601String(),
            $this->country,
        ]));
    }

    private function ensureConfigured(): void
    {
        foreach ([
            'services.thesportsdb.base_url' => $this->baseUrl,
            'services.thesportsdb.key' => $this->key,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                throw BroadcastFinderException::missingConfiguration($key);
            }
        }
    }

    private function responseErrorMessage(mixed $payload): string
    {
        if (! is_array($payload)) {
            return '';
        }

        try {
            return (string) json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return 'unreadable TheSportsDB error object';
        }
    }
}
