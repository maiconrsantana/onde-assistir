<?php

namespace App\Integrations\Gemini;

use App\Contracts\AiBroadcastFinder;
use App\Data\Broadcast\BroadcastChannelData;
use App\Data\Broadcast\BroadcastSearchResult;
use App\Exceptions\BroadcastFinderException;
use App\Models\Broadcaster;
use App\Models\BroadcastSource;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use App\Support\ExternalUrl;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class GeminiBroadcastFinder implements AiBroadcastFinder
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $key,
        private readonly ?string $model,
        private readonly bool $enabled = true,
        private readonly int $timeout = 30,
        private readonly int $retryTimes = 1,
        private readonly int $retrySleep = 1000,
    ) {}

    public function provider(): string
    {
        return BroadcastSource::PROVIDER_GEMINI;
    }

    public function findForFixture(FootballFixture $fixture): BroadcastSearchResult
    {
        $this->ensureConfigured();
        $fixture->loadMissing(['competition', 'homeTeam', 'awayTeam', 'broadcastSources']);

        $payload = $this->createResponse($fixture);
        $parsed = $this->parseStructuredOutput($payload);
        $evidence = $this->normalizeEvidence($parsed['evidence'] ?? [], $this->extractCitations($payload));
        $channels = $this->normalizeChannels($parsed['channels'] ?? []);
        $status = $this->normalizeStatus((string) ($parsed['status'] ?? BroadcastSource::RESULT_ERROR));

        if ($status === BroadcastSource::RESULT_FOUND && ($channels === [] || $evidence === [])) {
            $status = BroadcastSource::RESULT_UNCERTAIN;
        }

        return new BroadcastSearchResult(
            provider: $this->provider(),
            status: $status,
            queryHash: $this->queryHash($fixture),
            channels: $status === BroadcastSource::RESULT_FOUND ? $channels : [],
            evidence: $evidence,
            evidenceSummary: is_string($parsed['summary'] ?? null) ? $parsed['summary'] : null,
            rawResponse: $this->sanitizeRawResponse($payload),
            providerConfidence: $this->providerConfidence($parsed),
            calculatedConfidence: $this->calculateConfidence($status, $channels, $evidence),
            model: $this->model,
            tokensUsed: data_get($payload, 'usageMetadata.totalTokenCount'),
            webSearchCalls: $this->webSearchCalls($payload),
        );
    }

    private function createResponse(FootballFixture $fixture): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) $this->baseUrl, '/'))
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep, throw: false)
                ->withHeaders(['x-goog-api-key' => (string) $this->key])
                ->post('models/'.rawurlencode((string) $this->model).':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $this->developerInstructions()]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $this->fixturePrompt($fixture)]]]],
                    'tools' => [['google_search' => (object) []]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseJsonSchema' => $this->schema(),
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw BroadcastFinderException::requestFailed('Gemini', 0, $exception->getMessage());
        }

        if (! $response->successful()) {
            throw BroadcastFinderException::requestFailed('Gemini', $response->status(), $this->responseErrorMessage($response->json()));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw BroadcastFinderException::invalidPayload('Gemini', 'response body is not a JSON object');
        }

        return $payload;
    }

    private function developerInstructions(): string
    {
        return implode("\n", [
            'Voce pesquisa transmissoes oficiais de jogos de futebol no Brasil.',
            'Use a busca web. Nao use conhecimento proprio para completar canais.',
            'Considere apenas o confronto, data, horario, competicao e pais informados.',
            'Priorize fontes oficiais da competicao, clubes, emissoras e plataformas.',
            'Nao fabrique canais, URLs, datas, citacoes ou evidencias.',
            'Se nao houver fonte rastreavel suficiente, retorne not_found, uncertain ou conflicting.',
        ]);
    }

    private function fixturePrompt(FootballFixture $fixture): string
    {
        $startsAt = CarbonImmutable::parse($fixture->starts_at)->setTimezone(config('app.timezone', 'America/Sao_Paulo'))->format('Y-m-d H:i');

        return json_encode([
            'task' => 'Pesquisar onde assistir no Brasil.',
            'country' => 'BR',
            'competition' => $fixture->competition->name,
            'season' => $fixture->competition->season_name,
            'round' => $fixture->round,
            'home_team' => $fixture->homeTeam->name,
            'away_team' => $fixture->awayTeam->name,
            'starts_at' => $startsAt,
            'timezone' => config('app.timezone', 'America/Sao_Paulo'),
            'previous_sources' => $fixture->broadcastSources->map(fn (BroadcastSource $source): array => [
                'provider' => $source->provider,
                'status' => $source->result_status,
                'summary' => $source->evidence_summary,
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR);
    }

    private function schema(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['status', 'channels', 'evidence', 'summary', 'confidence'],
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['found', 'not_found', 'uncertain', 'conflicting']],
                'channels' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['name', 'type', 'access_type'], 'properties' => [
                    'name' => ['type' => 'string'],
                    'type' => ['type' => 'string', 'enum' => ['tv_open', 'tv_closed', 'streaming', 'youtube', 'other']],
                    'access_type' => ['type' => 'string', 'enum' => ['free', 'subscription', 'pay_per_view', 'unknown']],
                ]]],
                'evidence' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['url', 'publisher', 'published_at', 'summary'], 'properties' => [
                    'url' => ['type' => 'string'], 'publisher' => ['type' => 'string'], 'published_at' => ['type' => ['string', 'null']], 'summary' => ['type' => 'string'],
                ]]],
                'summary' => ['type' => 'string'], 'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            ],
        ];
    }

    private function parseStructuredOutput(array $payload): array
    {
        $text = collect(data_get($payload, 'candidates.0.content.parts', []))->pluck('text')->filter()->first();
        if (! is_string($text) || trim($text) === '') {
            throw BroadcastFinderException::invalidPayload('Gemini', 'missing output text');
        }

        try {
            $parsed = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw BroadcastFinderException::invalidPayload('Gemini', 'output text is not valid JSON: '.$exception->getMessage());
        }

        if (! is_array($parsed)) {
            throw BroadcastFinderException::invalidPayload('Gemini', 'structured output is not a JSON object');
        }

        return $parsed;
    }

    private function normalizeChannels(array $rows): array
    {
        return collect($rows)->filter(fn (mixed $row): bool => is_array($row))->map(function (array $row): ?BroadcastChannelData {
            $name = trim((string) ($row['name'] ?? ''));

            return $name === '' ? null : new BroadcastChannelData($name, $this->channelType((string) ($row['type'] ?? 'other')), $this->accessType((string) ($row['access_type'] ?? 'unknown')));
        })->filter()->unique(fn (BroadcastChannelData $channel): string => Str::lower($channel->name))->values()->all();
    }

    private function normalizeEvidence(array $rows, array $citations): array
    {
        $evidence = collect($rows)->filter(fn (mixed $row): bool => is_array($row))->map(function (array $row): ?array {
            $url = ExternalUrl::normalize(is_string($row['url'] ?? null) ? $row['url'] : null);

            return $url === null ? null : ['url' => $url, 'publisher' => is_string($row['publisher'] ?? null) ? $row['publisher'] : parse_url($url, PHP_URL_HOST), 'published_at' => is_string($row['published_at'] ?? null) ? $row['published_at'] : null, 'summary' => is_string($row['summary'] ?? null) ? $row['summary'] : ''];
        })->filter()->values();
        foreach ($citations as $citation) {
            if (! $evidence->contains('url', $citation['url'])) {
                $evidence->push($citation);
            }
        }

        return $evidence->unique('url')->values()->all();
    }

    private function extractCitations(array $payload): array
    {
        return collect(data_get($payload, 'candidates.0.groundingMetadata.groundingChunks', []))->map(function (mixed $chunk): ?array {
            $url = data_get($chunk, 'web.uri');
            $url = ExternalUrl::normalize(is_string($url) ? $url : null);

            return $url === null ? null : ['url' => $url, 'publisher' => data_get($chunk, 'web.title', parse_url($url, PHP_URL_HOST)), 'published_at' => null, 'summary' => 'Fonte retornada pela busca web do Gemini.'];
        })->filter()->unique('url')->values()->all();
    }

    private function sanitizeRawResponse(array $payload): array
    {
        return ['model' => $payload['modelVersion'] ?? $this->model, 'usageMetadata' => $payload['usageMetadata'] ?? null, 'candidates' => $payload['candidates'] ?? []];
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'found', 'confirmed' => BroadcastSource::RESULT_FOUND, 'not_found' => BroadcastSource::RESULT_NOT_FOUND, 'uncertain' => BroadcastSource::RESULT_UNCERTAIN, 'conflicting' => BroadcastSource::RESULT_CONFLICTING, default => BroadcastSource::RESULT_ERROR
        };
    }

    private function calculateConfidence(string $status, array $channels, array $evidence): float
    {
        return $status !== BroadcastSource::RESULT_FOUND || $channels === [] || $evidence === [] ? 0.0 : (count($evidence) >= 2 ? 0.9 : 0.75);
    }

    private function providerConfidence(array $parsed): ?float
    {
        return is_numeric($parsed['confidence'] ?? null) ? max(0, min(1, (float) $parsed['confidence'])) : null;
    }

    private function channelType(string $type): string
    {
        return in_array($type, [Broadcaster::TYPE_TV_OPEN, Broadcaster::TYPE_TV_CLOSED, Broadcaster::TYPE_STREAMING, Broadcaster::TYPE_YOUTUBE, Broadcaster::TYPE_OTHER], true) ? $type : Broadcaster::TYPE_OTHER;
    }

    private function accessType(string $type): string
    {
        return in_array($type, [FixtureBroadcast::ACCESS_FREE, FixtureBroadcast::ACCESS_SUBSCRIPTION, FixtureBroadcast::ACCESS_PAY_PER_VIEW, FixtureBroadcast::ACCESS_UNKNOWN], true) ? $type : FixtureBroadcast::ACCESS_UNKNOWN;
    }

    private function webSearchCalls(array $payload): int
    {
        return data_get($payload, 'candidates.0.groundingMetadata') ? 1 : 0;
    }

    private function queryHash(FootballFixture $fixture): string
    {
        return hash('sha256', implode('|', [$this->provider(), $fixture->id, $fixture->competition->name, $fixture->competition->season_name, $fixture->round, $fixture->homeTeam->name, $fixture->awayTeam->name, CarbonImmutable::parse($fixture->starts_at)->utc()->toIso8601String()]));
    }

    private function ensureConfigured(): void
    {
        if (! $this->enabled) {
            throw BroadcastFinderException::missingConfiguration('services.gemini.broadcast_search_enabled');
        }
        foreach (['services.gemini.base_url' => $this->baseUrl, 'services.gemini.key' => $this->key, 'services.gemini.model' => $this->model] as $key => $value) {
            if ($value === null || $value === '') {
                throw BroadcastFinderException::missingConfiguration($key);
            }
        }
    }

    private function responseErrorMessage(mixed $payload): string
    {
        if (is_array($payload) && is_string($message = data_get($payload, 'error.message'))) {
            return $message;
        }
        try {
            return (string) json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return 'unreadable Gemini error object';
        }
    }
}
