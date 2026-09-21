<?php

namespace App\Console\Commands;

use App\Contracts\AiBroadcastFinder;
use App\Models\FootballFixture;
use App\Services\Broadcast\BroadcastResolver;
use App\Services\Operations\FootballAutomationStatus;
use App\Services\Operations\PublicScheduleCache;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResolveOpenAiBroadcastsCommand extends Command
{
    protected $signature = 'football:resolve-openai-broadcasts
        {--from= : Start date in YYYY-MM-DD format}
        {--to= : End date in YYYY-MM-DD format}
        {--days=7 : Number of days to include when --to is not provided}
        {--limit=10 : Maximum number of fixtures to process}
        {--ttl-hours=24 : Hours to wait before repeating an OpenAI search}
        {--force : Ignore the OpenAI search TTL}';

    protected $description = 'Resolve missing or uncertain fixture broadcasts using the configured AI web search fallback.';

    public function handle(
        AiBroadcastFinder $finder,
        FootballAutomationStatus $status,
        PublicScheduleCache $cache,
    ): int {
        $startedAt = microtime(true);
        [$from, $to] = $this->dateRange();

        Log::info('football.resolve_openai.started', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
        $limit = max(1, (int) $this->option('limit'));
        $ttlHours = max(1, (int) $this->option('ttl-hours'));
        $cutoff = now()->utc()->subHours($ttlHours);
        $provider = $finder->provider();

        $fixtures = FootballFixture::query()
            ->with(['competition', 'homeTeam', 'awayTeam', 'broadcastSources'])
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->where('starts_at', '>=', now()->utc())
            ->whereIn('resolution_status', [
                FootballFixture::RESOLUTION_NOT_FOUND,
                FootballFixture::RESOLUTION_UNCERTAIN,
                FootballFixture::RESOLUTION_CONFLICTING,
                FootballFixture::RESOLUTION_ERROR,
            ])
            ->when(! $this->option('force'), fn ($query) => $query->whereDoesntHave(
                'broadcastSources',
                fn ($sourceQuery) => $sourceQuery
                    ->where('provider', $provider)
                    ->where('queried_at', '>=', $cutoff),
            ))
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        $this->components->info("Resolvendo transmissoes com {$finder->provider()} para {$fixtures->count()} partida(s).");

        $resolver = new BroadcastResolver($finder, $finder->provider());
        $result = $resolver->resolve($fixtures);

        $this->table(['Metrica', 'Total'], collect($result->totals())
            ->map(fn (int $value, string $key): array => [$key, $value])
            ->values()
            ->all());

        foreach ($result->messages as $message) {
            $this->components->warn($message);
        }

        if ($result->errors > 0) {
            Log::warning('football.resolve_openai.completed_with_errors', [
                'duration_ms' => $this->durationMs($startedAt),
                'totals' => $result->totals(),
            ]);
            $status->recordFailure('football:resolve-openai-broadcasts', "Resolucao {$provider} concluiu com erros.", $result->totals());

            return self::FAILURE;
        }

        $cache->invalidate();
        $payload = array_merge($result->totals(), [
            'duration_ms' => $this->durationMs($startedAt),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'fixtures_selected' => $fixtures->count(),
        ]);
        Log::info('football.resolve_ai.completed', ['provider' => $provider, 'totals' => $payload]);
        $status->recordSuccess('football:resolve-openai-broadcasts', $payload);

        return self::SUCCESS;
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateRange(): array
    {
        $timezone = config('app.timezone', 'America/Sao_Paulo');

        $from = $this->option('from')
            ? CarbonImmutable::parse((string) $this->option('from'), $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();

        $to = $this->option('to')
            ? CarbonImmutable::parse((string) $this->option('to'), $timezone)->endOfDay()
            : $from->addDays(max(0, (int) $this->option('days')))->endOfDay();

        if ($to->lessThan($from)) {
            $this->fail('A data final nao pode ser anterior a data inicial.');
        }

        return [$from, $to];
    }
}
