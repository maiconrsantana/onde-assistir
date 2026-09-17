<?php

namespace App\Console\Commands;

use App\Models\FootballFixture;
use App\Services\Broadcast\BroadcastResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ResolveBroadcastsCommand extends Command
{
    protected $signature = 'football:resolve-broadcasts
        {--from= : Start date in YYYY-MM-DD format}
        {--to= : End date in YYYY-MM-DD format}
        {--days=7 : Number of days to include when --to is not provided}
        {--limit=50 : Maximum number of fixtures to process}
        {--status=pending : Resolution status to process, or all}';

    protected $description = 'Resolve fixture broadcasts using TheSportsDB before any OpenAI fallback.';

    public function handle(BroadcastResolver $resolver): int
    {
        [$from, $to] = $this->dateRange();
        $limit = max(1, (int) $this->option('limit'));
        $status = (string) $this->option('status');

        $fixtures = FootballFixture::query()
            ->with(['competition', 'homeTeam', 'awayTeam'])
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->when($status !== 'all', fn ($query) => $query->where('resolution_status', $status))
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        $this->components->info("Resolvendo transmissoes de {$fixtures->count()} partida(s).");

        $result = $resolver->resolve($fixtures);

        $this->table(['Metrica', 'Total'], collect($result->totals())
            ->map(fn (int $value, string $key): array => [$key, $value])
            ->values()
            ->all());

        foreach ($result->messages as $message) {
            $this->components->warn($message);
        }

        return $result->errors > 0 ? self::FAILURE : self::SUCCESS;
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
