<?php

namespace App\Integrations\OpenAI;

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

class OpenAIBroadcastFinder implements AiBroadcastFinder
{
    private const PROVIDER = BroadcastSource::PROVIDER_OPENAI;

    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $key,
        private readonly ?string $model,
        private readonly bool $enabled = true,
        private readonly string $webSearchTool = 'web_search_preview',
        private readonly string $webSearchContextSize = 'low',
        private readonly int $timeout = 30,
        private readonly int $retryTimes = 1,
        private readonly int $retrySleep = 1000,
    ) {}

    public function provider(): string
    {
        return BroadcastSource::PROVIDER_OPENAI;
    }

    public function findForFixture(FootballFixture $fixture): BroadcastSearchResult
    {
        $this->ensureConfigured();

        $fixture->loadMissing(['competition', 'homeTeam', 'awayTeam', 'broadcastSources']);

        $queryHash = $this->queryHash($fixture);
        $payload = $this->createResponse($fixture);
        $parsed = $this->parseStructuredOutput($payload);
        $citations = $this->extractCitations($payload);
        $evidence = $this->normalizeEvidence($parsed['evidence'] ?? [], $citations);
        $channels = $this->normalizeChannels($parsed['channels'] ?? []);
        $status = $this->normalizeStatus((string) ($parsed['status'] ?? BroadcastSource::RESULT_ERROR));
        $calculatedConfidence = $this->calculateConfidence($status, $channels, $evidence);

        if ($status === BroadcastSource::RESULT_FOUND && ($channels === [] || $evidence === [])) {
            $status = BroadcastSource::RESULT_UNCERTAIN;
        }

        return new BroadcastSearchResult(
            provider: self::PROVIDER,
            status: $status,
            queryHash: $queryHash,
            channels: $status === BroadcastSource::RESULT_FOUND ? $channels : [],
            evidence: $evidence,
            evidenceSummary: $this->evidenceSummary($parsed),
            rawResponse: $this->sanitizeRawResponse($payload),
            providerConfidence: $this->providerConfidence($parsed),
            calculatedConfidence: $calculatedConfidence,
            model: $this->model,
            tokensUsed: data_get($payload, 'usage.total_tokens'),
            webSearchCalls: $this->webSearchCalls($payload),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createResponse(FootballFixture $fixture): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) $this->baseUrl, '/'))
                ->acceptJson()
                ->asJson()
                ->withToken((string) $this->key)
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep, throw: false)
                ->post('responses', [
                    'model' => $this->model,
                    'tools' => [[
                        'type' => $this->webSearchTool,
                        'search_context_size' => $this->webSearchContextSize,
                        'user_location' => [
                            'type' => 'approximate',
                            'country' => 'BR',
                            'timezone' => config('app.timezone', 'America/Sao_Paulo'),
                        ],
                    ]],
                    'input' => [
                        [
                            'role' => 'developer',
                            'content' => $this->developerInstructions(),
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->fixturePrompt($fixture),
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'broadcast_search_result',
                            'strict' => true,
                            'schema' => $this->schema(),
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw BroadcastFinderException::requestFailed('OpenAI', 0, $exception->getMessage());
        }

        if (! $response->successful()) {
            throw BroadcastFinderException::requestFailed('OpenAI', $response->status(), $this->responseErrorMessage($response->json()));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw BroadcastFinderException::invalidPayload('OpenAI', 'response body is not a JSON object');
        }

        if (($payload['status'] ?? 'completed') !== 'completed') {
            throw BroadcastFinderException::invalidPayload('OpenAI', 'response status is not completed');
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
            'Use portais jornalisticos reconhecidos apenas como evidencia secundaria.',
            'Nao fabrique canais, URLs, datas, citacoes ou evidencias.',
            'Se nao houver fonte rastreavel suficiente, retorne not_found, uncertain ou conflicting.',
        ]);
    }

    private function fixturePrompt(FootballFixture $fixture): string
    {
        $startsAt = CarbonImmutable::parse($fixture->starts_at)
            ->setTimezone(config('app.timezone', 'America/Sao_Paulo'))
            ->format('Y-m-d H:i');

        $context = $fixture->broadcastSources
            ->map(fn (BroadcastSource $source): array => [
                'provider' => $source->provider,
                'status' => $source->result_status,
                'summary' => $source->evidence_summary,
            ])
            ->values()
            ->all();

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
            'previous_sources' => $context,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['status', 'channels', 'evidence', 'summary', 'confidence'],
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['found', 'not_found', 'uncertain', 'conflicting'],
                ],
                'channels' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'type', 'access_type'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'type' => [
                                'type' => 'string',
                                'enum' => ['tv_open', 'tv_closed', 'streaming', 'youtube', 'other'],
                            ],
                            'access_type' => [
                                'type' => 'string',
                                'enum' => ['free', 'subscription', 'pay_per_view', 'unknown'],
                            ],
                        ],
                    ],
                ],
                'evidence' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['url', 'publisher', 'published_at', 'summary'],
                        'properties' => [
                            'url' => ['type' => 'string'],
                            'publisher' => ['type' => 'string'],
                            'published_at' => ['type' => ['string', 'null']],
                            'summary' => ['type' => 'string'],
                        ],
                    ],
                ],
                'summary' => ['type' => 'string'],
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function parseStructuredOutput(array $payload): array
    {
        $text = $this->extractOutputText($payload);

        if ($text === null || trim($text) === '') {
            throw BroadcastFinderException::invalidPayload('OpenAI', 'missing output text');
        }

        try {
            $parsed = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw BroadcastFinderException::invalidPayload('OpenAI', 'output text is not valid JSON: '.$exception->getMessage());
        }

        if (! is_array($parsed)) {
            throw BroadcastFinderException::invalidPayload('OpenAI', 'structured output is not a JSON object');
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOutputText(array $payload): ?string
    {
        if (is_string($payload['output_text'] ?? null)) {
            return $payload['output_text'];
        }

        foreach (($payload['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, BroadcastChannelData>
     */
    private function normalizeChannels(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): ?BroadcastChannelData {
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return new BroadcastChannelData(
                    name: $name,
                    type: $this->channelType((string) ($row['type'] ?? Broadcaster::TYPE_OTHER)),
                    accessType: $this->accessType((string) ($row['access_type'] ?? FixtureBroadcast::ACCESS_UNKNOWN)),
                );
            })
            ->filter()
            ->unique(fn (BroadcastChannelData $channel): string => Str::lower($channel->name))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $rows
     * @param  array<int, array<string, string|null>>  $citations
     * @return array<int, array<string, string|null>>
     */
    private function normalizeEvidence(array $rows, array $citations): array
    {
        $evidence = collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): ?array {
                $url = $row['url'] ?? null;

                $url = ExternalUrl::normalize(is_string($url) ? $url : null);

                if ($url === null) {
                    return null;
                }

                return [
                    'url' => $url,
                    'publisher' => is_string($row['publisher'] ?? null) ? $row['publisher'] : parse_url($url, PHP_URL_HOST),
                    'published_at' => is_string($row['published_at'] ?? null) ? $row['published_at'] : null,
                    'summary' => is_string($row['summary'] ?? null) ? $row['summary'] : '',
                ];
            })
            ->filter()
            ->values();

        foreach ($citations as $citation) {
            if (! $evidence->contains('url', $citation['url'])) {
                $evidence->push($citation);
            }
        }

        return $evidence
            ->unique('url')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, string|null>>
     */
    private function extractCitations(array $payload): array
    {
        $citations = [];

        foreach (($payload['output'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'web_search_call') {
                foreach ((array) data_get($item, 'action.sources', []) as $source) {
                    $url = is_array($source) ? ($source['url'] ?? null) : null;

                    $url = ExternalUrl::normalize(is_string($url) ? $url : null);

                    if ($url !== null) {
                        $citations[] = [
                            'url' => $url,
                            'publisher' => parse_url($url, PHP_URL_HOST) ?: 'Fonte web',
                            'published_at' => null,
                            'summary' => 'Fonte retornada pela busca web da OpenAI.',
                        ];
                    }
                }
            }

            foreach ((array) ($item['content'] ?? []) as $content) {
                foreach ((array) data_get($content, 'annotations', []) as $annotation) {
                    $url = is_array($annotation) ? ($annotation['url'] ?? null) : null;

                    $url = ExternalUrl::normalize(is_string($url) ? $url : null);

                    if ($url !== null) {
                        $citations[] = [
                            'url' => $url,
                            'publisher' => is_string($annotation['title'] ?? null) ? $annotation['title'] : parse_url($url, PHP_URL_HOST),
                            'published_at' => null,
                            'summary' => 'Citacao retornada pela resposta da OpenAI.',
                        ];
                    }
                }
            }
        }

        return collect($citations)->unique('url')->values()->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeRawResponse(array $payload): array
    {
        return [
            'id' => $payload['id'] ?? null,
            'status' => $payload['status'] ?? null,
            'model' => $payload['model'] ?? $this->model,
            'usage' => $payload['usage'] ?? null,
            'output' => $payload['output'] ?? [],
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'found', 'confirmed' => BroadcastSource::RESULT_FOUND,
            BroadcastSource::RESULT_NOT_FOUND => BroadcastSource::RESULT_NOT_FOUND,
            BroadcastSource::RESULT_UNCERTAIN => BroadcastSource::RESULT_UNCERTAIN,
            BroadcastSource::RESULT_CONFLICTING => BroadcastSource::RESULT_CONFLICTING,
            default => BroadcastSource::RESULT_ERROR,
        };
    }

    /**
     * @param  array<int, BroadcastChannelData>  $channels
     * @param  array<int, array<string, string|null>>  $evidence
     */
    private function calculateConfidence(string $status, array $channels, array $evidence): float
    {
        if ($status !== BroadcastSource::RESULT_FOUND || $channels === [] || $evidence === []) {
            return 0.0;
        }

        return count($evidence) >= 2 ? 0.9 : 0.75;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function providerConfidence(array $parsed): ?float
    {
        $confidence = $parsed['confidence'] ?? null;

        return is_numeric($confidence) ? max(0, min(1, (float) $confidence)) : null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function evidenceSummary(array $parsed): ?string
    {
        return is_string($parsed['summary'] ?? null) ? $parsed['summary'] : null;
    }

    private function channelType(string $type): string
    {
        return in_array($type, [
            Broadcaster::TYPE_TV_OPEN,
            Broadcaster::TYPE_TV_CLOSED,
            Broadcaster::TYPE_STREAMING,
            Broadcaster::TYPE_YOUTUBE,
            Broadcaster::TYPE_OTHER,
        ], true) ? $type : Broadcaster::TYPE_OTHER;
    }

    private function accessType(string $type): string
    {
        return in_array($type, [
            FixtureBroadcast::ACCESS_FREE,
            FixtureBroadcast::ACCESS_SUBSCRIPTION,
            FixtureBroadcast::ACCESS_PAY_PER_VIEW,
            FixtureBroadcast::ACCESS_UNKNOWN,
        ], true) ? $type : FixtureBroadcast::ACCESS_UNKNOWN;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function webSearchCalls(array $payload): int
    {
        return collect($payload['output'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'web_search_call')
            ->count();
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
        ]));
    }

    private function ensureConfigured(): void
    {
        if (! $this->enabled) {
            throw BroadcastFinderException::missingConfiguration('services.openai.broadcast_search_enabled');
        }

        foreach ([
            'services.openai.base_url' => $this->baseUrl,
            'services.openai.key' => $this->key,
            'services.openai.model' => $this->model,
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

        $message = data_get($payload, 'error.message');

        if (is_string($message)) {
            return $message;
        }

        try {
            return (string) json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return 'unreadable OpenAI error object';
        }
    }
}
