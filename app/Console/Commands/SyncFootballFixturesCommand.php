<?php

namespace App\Console\Commands;

use App\Contracts\FootballDataProvider;
use App\Services\Football\FixtureSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class SyncFootballFixturesCommand extends Command
{
    protected $signature = 'football:sync
        {--from= : Start date in YYYY-MM-DD format}
        {--to= : End date in YYYY-MM-DD format}
        {--days=7 : Number of days to include when --to is not provided}';

    protected $description = 'Fetch API-Football fixtures and persist them idempotently.';

    public function handle(FootballDataProvider $provider, FixtureSynchronizer $synchronizer): int
    {
        [$from, $to] = $this->dateRange();

        $this->components->info("Sincronizando partidas de {$from->toDateString()} ate {$to->toDateString()}.");

        try {
            $fixtures = $provider->fixturesBetween($from, $to);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $result = $synchronizer->sync($fixtures);

        $this->table(['Metrica', 'Total'], collect($result->totals())
            ->map(fn (int $value, string $key): array => [$key, $value])
            ->values()
            ->all());

        foreach ($result->errorMessages as $message) {
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
