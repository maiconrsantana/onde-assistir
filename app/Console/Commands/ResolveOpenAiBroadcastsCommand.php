<?php

namespace App\Console\Commands;

use App\Integrations\OpenAI\OpenAIBroadcastFinder;
use App\Models\BroadcastSource;
use App\Models\FootballFixture;
use App\Services\Broadcast\BroadcastResolver;
use App\Services\Operations\FootballAutomationStatus;
use App\Services\Operations\PublicScheduleCache;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ResolveOpenAiBroadcastsCommand extends Command
{
    protected $signature = 'football:resolve-openai-broadcasts
        {--from= : Start date in YYYY-MM-DD format}
        {--to= : End date in YYYY-MM-DD format}
        {--days=7 : Number of days to include when --to is not provided}
        {--limit=10 : Maximum number of fixtures to process}
        {--ttl-hours=24 : Hours to wait before repeating an OpenAI search}
        {--force : Ignore the OpenAI search TTL}';

    protected $description = 'Resolve missing or uncertain fixture broadcasts using OpenAI web search fallback.';

    public function handle(
        OpenAIBroadcastFinder $finder,
        FootballAutomationStatus $status,
        PublicScheduleCache $cache,
    ): int {
        [$from, $to] = $this->dateRange();
        $limit = max(1, (int) $this->option('limit'));
        $ttlHours = max(1, (int) $this->option('ttl-hours'));
        $cutoff = now()->utc()->subHours($ttlHours);

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
                    ->where('provider', BroadcastSource::PROVIDER_OPENAI)
                    ->where('queried_at', '>=', $cutoff),
            ))
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        $this->components->info("Resolvendo transmissoes com OpenAI para {$fixtures->count()} partida(s).");

        $resolver = new BroadcastResolver($finder, BroadcastSource::PROVIDER_OPENAI);
        $result = $resolver->resolve($fixtures);

        $this->table(['Metrica', 'Total'], collect($result->totals())
            ->map(fn (int $value, string $key): array => [$key, $value])
            ->values()
            ->all());

        foreach ($result->messages as $message) {
            $this->components->warn($message);
        }

        if ($result->errors > 0) {
            $status->recordFailure('football:resolve-openai-broadcasts', 'Resolucao OpenAI concluiu com erros.', $result->totals());

            return self::FAILURE;
        }

        $cache->invalidate();
        $status->recordSuccess('football:resolve-openai-broadcasts', $result->totals());

        return self::SUCCESS;
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
