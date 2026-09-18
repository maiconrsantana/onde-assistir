<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;

class FootballAutomationStatus
{
    /**
     * @return array<int, string>
     */
    public function commands(): array
    {
        return [
            'football:sync',
            'football:resolve-broadcasts',
            'football:resolve-openai-broadcasts',
            'football:refresh-broadcasts',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordSuccess(string $command, array $payload = []): void
    {
        Cache::forever($this->key($command), [
            'command' => $command,
            'last_status' => 'success',
            'last_success_at' => now()->utc()->toIso8601String(),
            'last_failure_at' => $this->statusFor($command)['last_failure_at'] ?? null,
            'message' => null,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordFailure(string $command, string $message, array $payload = []): void
    {
        $previous = $this->statusFor($command);

        Cache::forever($this->key($command), [
            'command' => $command,
            'last_status' => 'failure',
            'last_success_at' => $previous['last_success_at'] ?? null,
            'last_failure_at' => now()->utc()->toIso8601String(),
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(fn (string $command): array => $this->statusFor($command), $this->commands());
    }

    /**
     * @return array<string, mixed>
     */
    public function statusFor(string $command): array
    {
        return Cache::get($this->key($command), [
            'command' => $command,
            'last_status' => 'never_run',
            'last_success_at' => null,
            'last_failure_at' => null,
            'message' => null,
            'payload' => [],
        ]);
    }

    private function key(string $command): string
    {
        return 'football.automation.'.str_replace(':', '.', $command);
    }
}
