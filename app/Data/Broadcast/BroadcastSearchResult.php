<?php

namespace App\Data\Broadcast;

use App\Models\BroadcastSource;

final readonly class BroadcastSearchResult
{
    /**
     * @param  array<int, BroadcastChannelData>  $channels
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $provider,
        public string $status,
        public string $queryHash,
        public array $channels = [],
        public array $evidence = [],
        public ?string $evidenceSummary = null,
        public array $rawResponse = [],
        public ?string $externalEventId = null,
        public ?float $providerConfidence = null,
        public ?float $calculatedConfidence = null,
        public ?float $matchScore = null,
        public ?string $model = null,
        public ?int $tokensUsed = null,
        public ?int $webSearchCalls = null,
    ) {}

    public function found(): bool
    {
        return $this->status === BroadcastSource::RESULT_FOUND && $this->channels !== [];
    }
}
