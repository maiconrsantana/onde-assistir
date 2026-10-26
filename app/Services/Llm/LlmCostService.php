<?php

namespace App\Services\Llm;

use App\Exceptions\LlmBudgetExceededException;
use App\Models\FootballFixture;
use App\Models\LlmBudget;
use App\Models\LlmUsageRecord;
use App\Services\Automation\AutomationRunContext;
use Carbon\CarbonImmutable;

class LlmCostService
{
    public function assertCanCall(string $provider): void
    {
        $budget = LlmBudget::ensureFor($provider);
        if (! $budget->enabled) {
            throw new LlmBudgetExceededException("Chamadas para {$provider} estao bloqueadas pelo painel.");
        }

        $reservation = (float) ($budget->max_per_request ?? 0);
        $today = $this->totalSince($provider, CarbonImmutable::now()->startOfDay());
        $month = $this->totalSince($provider, CarbonImmutable::now()->startOfMonth());

        if ($budget->daily_limit !== null && $today + $reservation > (float) $budget->daily_limit) {
            throw new LlmBudgetExceededException("Limite diario de {$provider} atingido.");
        }

        if ($budget->monthly_limit !== null && $month + $reservation > (float) $budget->monthly_limit) {
            throw new LlmBudgetExceededException("Limite mensal de {$provider} atingido.");
        }
    }

    /** @param array<string, mixed> $payload */
    public function recordSuccess(
        string $provider,
        ?string $model,
        ?FootballFixture $fixture,
        array $payload,
        ?int $automationRunId = null,
    ): LlmUsageRecord {
        $usage = $this->usageFor($provider, $payload);

        return LlmUsageRecord::query()->create([
            'provider' => $provider,
            'model' => $model,
            'football_fixture_id' => $fixture?->getKey(),
            'automation_run_id' => $automationRunId ?? app(AutomationRunContext::class)->id(),
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'total_tokens' => $usage['total_tokens'],
            'estimated_cost' => $this->estimate($provider, $usage['input_tokens'], $usage['output_tokens']),
            'currency' => 'USD',
            'status' => 'success',
            'metadata' => ['usage' => $usage],
            'requested_at' => now()->utc(),
            'completed_at' => now()->utc(),
        ]);
    }

    /** @param array<string, mixed> $usage */
    private function estimate(string $provider, ?int $input, ?int $output): float
    {
        $prices = config("services.llm_prices.{$provider}", []);

        return (($input ?? 0) / 1_000_000 * (float) ($prices['input_per_million'] ?? 0))
            + (($output ?? 0) / 1_000_000 * (float) ($prices['output_per_million'] ?? 0));
    }

    /** @return array{input_tokens: ?int, output_tokens: ?int, total_tokens: ?int} */
    private function usageFor(string $provider, array $payload): array
    {
        if ($provider === 'openai') {
            return [
                'input_tokens' => data_get($payload, 'usage.input_tokens'),
                'output_tokens' => data_get($payload, 'usage.output_tokens'),
                'total_tokens' => data_get($payload, 'usage.total_tokens'),
            ];
        }

        return [
            'input_tokens' => data_get($payload, 'usageMetadata.promptTokenCount'),
            'output_tokens' => data_get($payload, 'usageMetadata.candidatesTokenCount'),
            'total_tokens' => data_get($payload, 'usageMetadata.totalTokenCount'),
        ];
    }

    private function totalSince(string $provider, CarbonImmutable $since): float
    {
        return (float) LlmUsageRecord::query()
            ->where('provider', $provider)
            ->where('status', 'success')
            ->where('created_at', '>=', $since->utc())
            ->sum('estimated_cost');
    }
}
