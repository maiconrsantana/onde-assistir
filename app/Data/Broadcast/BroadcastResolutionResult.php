<?php

namespace App\Data\Broadcast;

use App\Models\BroadcastSource;

final class BroadcastResolutionResult
{
    public int $resolved = 0;

    public int $notFound = 0;

    public int $uncertain = 0;

    public int $conflicting = 0;

    public int $errors = 0;

    public int $broadcastsCreated = 0;

    public int $broadcastsUpdated = 0;

    /**
     * @var array<int, string>
     */
    public array $messages = [];

    public function recordStatus(string $status): void
    {
        match ($status) {
            BroadcastSource::RESULT_FOUND => $this->resolved++,
            BroadcastSource::RESULT_NOT_FOUND => $this->notFound++,
            BroadcastSource::RESULT_UNCERTAIN => $this->uncertain++,
            BroadcastSource::RESULT_CONFLICTING => $this->conflicting++,
            default => $this->errors++,
        };
    }

    public function recordBroadcast(bool $created): void
    {
        $created ? $this->broadcastsCreated++ : $this->broadcastsUpdated++;
    }

    public function recordMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @return array<string, int>
     */
    public function totals(): array
    {
        return [
            'resolved' => $this->resolved,
            'not_found' => $this->notFound,
            'uncertain' => $this->uncertain,
            'conflicting' => $this->conflicting,
            'errors' => $this->errors,
            'broadcasts_created' => $this->broadcastsCreated,
            'broadcasts_updated' => $this->broadcastsUpdated,
        ];
    }
}
