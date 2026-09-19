<?php

namespace App\Services\Broadcast;

use App\Contracts\BroadcastFinder;
use App\Data\Broadcast\BroadcastChannelData;
use App\Data\Broadcast\BroadcastResolutionResult;
use App\Data\Broadcast\BroadcastSearchResult;
use App\Models\Broadcaster;
use App\Models\BroadcastSource;
use App\Models\FixtureBroadcast;
use App\Models\FixtureProviderMapping;
use App\Models\FootballFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BroadcastResolver
{
    public function __construct(
        private readonly BroadcastFinder $finder,
        private readonly string $failureProvider = BroadcastSource::PROVIDER_THESPORTSDB,
    ) {}

    /**
     * @param  iterable<int, FootballFixture>  $fixtures
     */
    public function resolve(iterable $fixtures): BroadcastResolutionResult
    {
        $result = new BroadcastResolutionResult;

        foreach ($fixtures as $fixture) {
            $fixture->loadMissing(['competition', 'homeTeam', 'awayTeam']);

            try {
                $searchResult = $this->finder->findForFixture($fixture);

                DB::transaction(function () use ($fixture, $searchResult, $result): void {
                    $source = $this->persistSource($fixture, $searchResult);

                    $result->recordStatus($searchResult->status);

                    if ($searchResult->externalEventId !== null) {
                        $this->persistMapping($fixture, $searchResult);
                    }

                    if ($searchResult->found()) {
                        BroadcastSource::query()
                            ->where('football_fixture_id', $fixture->id)
                            ->whereKeyNot($source->id)
                            ->update(['selected' => false]);

                        foreach ($searchResult->channels as $channel) {
                            $broadcast = $this->persistBroadcast($fixture, $source, $channel, $searchResult);
                            $result->recordBroadcast($broadcast->wasRecentlyCreated);
                        }
                    }

                    $this->updateFixtureStatus($fixture, $searchResult);
                });
            } catch (Throwable $exception) {
                $this->recordFailure($fixture, $exception, $result);
            }
        }

        return $result;
    }

    private function persistSource(FootballFixture $fixture, BroadcastSearchResult $searchResult): BroadcastSource
    {
        return BroadcastSource::updateOrCreate([
            'provider' => $searchResult->provider,
            'query_hash' => $searchResult->queryHash,
        ], [
            'football_fixture_id' => $fixture->id,
            'external_event_id' => $searchResult->externalEventId,
            'channels' => $this->channelsToArray($searchResult->channels),
            'evidence' => $searchResult->evidence,
            'evidence_summary' => $searchResult->evidenceSummary,
            'raw_response' => $searchResult->rawResponse,
            'model' => $searchResult->model,
            'tokens_used' => $searchResult->tokensUsed,
            'web_search_calls' => $searchResult->webSearchCalls,
            'provider_confidence' => $searchResult->providerConfidence,
            'calculated_confidence' => $searchResult->calculatedConfidence,
            'result_status' => $searchResult->status,
            'selected' => $searchResult->found(),
            'queried_at' => now()->utc(),
            'validated_at' => $searchResult->found() ? now()->utc() : null,
        ]);
    }

    private function persistMapping(FootballFixture $fixture, BroadcastSearchResult $searchResult): void
    {
        FixtureProviderMapping::updateOrCreate([
            'provider' => $searchResult->provider,
            'external_event_id' => $searchResult->externalEventId,
        ], [
            'football_fixture_id' => $fixture->id,
            'match_score' => $searchResult->matchScore,
            'matched_at' => now()->utc(),
            'raw_payload' => [
                'query_hash' => $searchResult->queryHash,
                'status' => $searchResult->status,
            ],
        ]);
    }

    private function persistBroadcast(
        FootballFixture $fixture,
        BroadcastSource $source,
        BroadcastChannelData $channel,
        BroadcastSearchResult $searchResult,
    ): FixtureBroadcast {
        $broadcaster = Broadcaster::firstOrCreate([
            'slug' => $this->broadcasterSlug($channel->name),
        ], [
            'name' => $channel->name,
            'type' => $channel->type,
        ]);

        $existing = FixtureBroadcast::query()
            ->where('football_fixture_id', $fixture->id)
            ->where('broadcaster_id', $broadcaster->id)
            ->where('country_code', $channel->countryCode)
            ->first();

        if ($existing?->source_type === FixtureBroadcast::SOURCE_MANUAL) {
            return $existing;
        }

        return FixtureBroadcast::updateOrCreate([
            'football_fixture_id' => $fixture->id,
            'broadcaster_id' => $broadcaster->id,
            'country_code' => $channel->countryCode,
        ], [
            'broadcast_source_id' => $source->id,
            'access_type' => $channel->accessType,
            'source_type' => $searchResult->provider,
            'source_url' => $channel->sourceUrl ?? $this->firstEvidenceUrl($searchResult),
            'confidence' => $searchResult->calculatedConfidence,
            'verified_at' => now()->utc(),
            'needs_review' => true,
            'review_status' => FixtureBroadcast::REVIEW_PENDING,
            'publication_status' => FixtureBroadcast::PUBLICATION_DRAFT,
            'notes' => "Transmissao encontrada automaticamente via {$searchResult->provider}; aguardando revisao manual.",
        ]);
    }

    private function updateFixtureStatus(FootballFixture $fixture, BroadcastSearchResult $searchResult): void
    {
        $fixture->forceFill([
            'resolution_status' => match ($searchResult->status) {
                BroadcastSource::RESULT_FOUND => FootballFixture::RESOLUTION_RESOLVED,
                BroadcastSource::RESULT_NOT_FOUND => FootballFixture::RESOLUTION_NOT_FOUND,
                BroadcastSource::RESULT_UNCERTAIN => FootballFixture::RESOLUTION_UNCERTAIN,
                BroadcastSource::RESULT_CONFLICTING => FootballFixture::RESOLUTION_CONFLICTING,
                default => FootballFixture::RESOLUTION_ERROR,
            },
            'review_status' => FootballFixture::REVIEW_PENDING,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
            'resolution_hash' => $searchResult->queryHash,
            'resolved_at' => now()->utc(),
            'resolution_invalidated_at' => null,
        ])->save();
    }

    private function recordFailure(FootballFixture $fixture, Throwable $exception, BroadcastResolutionResult $result): void
    {
        $queryHash = hash('sha256', implode('|', [
            $this->failureProvider,
            $fixture->id,
            $fixture->external_id,
            $fixture->starts_at,
            'error',
        ]));

        DB::transaction(function () use ($fixture, $exception, $queryHash, $result): void {
            BroadcastSource::updateOrCreate([
                'provider' => $this->failureProvider,
                'query_hash' => $queryHash,
            ], [
                'football_fixture_id' => $fixture->id,
                'channels' => [],
                'evidence' => [],
                'evidence_summary' => $exception->getMessage(),
                'raw_response' => [
                    'exception' => $exception::class,
                ],
                'result_status' => BroadcastSource::RESULT_ERROR,
                'selected' => false,
                'queried_at' => now()->utc(),
                'validated_at' => null,
            ]);

            $fixture->forceFill([
                'resolution_status' => FootballFixture::RESOLUTION_ERROR,
                'review_status' => FootballFixture::REVIEW_PENDING,
                'publication_status' => FootballFixture::PUBLICATION_DRAFT,
                'resolution_hash' => $queryHash,
                'resolved_at' => now()->utc(),
            ])->save();

            $result->recordStatus(BroadcastSource::RESULT_ERROR);
            $result->recordMessage("Fixture {$fixture->id} failed: {$exception->getMessage()}");
        });

        Log::warning('football.broadcast_resolution_failed', [
            'football_fixture_id' => $fixture->id,
            'provider' => $this->failureProvider,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * @param  array<int, BroadcastChannelData>  $channels
     * @return array<int, array<string, string|null>>
     */
    private function channelsToArray(array $channels): array
    {
        return array_map(fn (BroadcastChannelData $channel): array => [
            'name' => $channel->name,
            'type' => $channel->type,
            'access_type' => $channel->accessType,
            'country_code' => $channel->countryCode,
            'source_url' => $channel->sourceUrl,
        ], $channels);
    }

    private function broadcasterSlug(string $name): string
    {
        return Str::slug($name) ?: 'broadcaster';
    }

    private function firstEvidenceUrl(BroadcastSearchResult $searchResult): ?string
    {
        $url = data_get($searchResult->evidence, '0.url');

        return is_string($url) && Str::startsWith($url, ['http://', 'https://']) ? $url : null;
    }
}
