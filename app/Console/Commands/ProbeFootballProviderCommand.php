<?php

namespace App\Console\Commands;

use App\Contracts\FootballDataProvider;
use App\Data\Football\FootballFixtureData;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class ProbeFootballProviderCommand extends Command
{
    protected $signature = 'football:probe-provider
        {--from= : Start date in YYYY-MM-DD format}
        {--to= : End date in YYYY-MM-DD format}
        {--days=7 : Number of days to include when --to is not provided}
        {--json : Output normalized fixtures as JSON}';

    protected $description = 'Fetch and summarize football provider fixtures without persisting data.';

    public function handle(FootballDataProvider $provider): int
    {
        [$from, $to] = $this->dateRange();

        $this->components->info("Consultando partidas de {$from->toDateString()} ate {$to->toDateString()}.");

        try {
            $fixtures = $provider->fixturesBetween($from, $to);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode(array_map(
                fn (FootballFixtureData $fixture): array => $this->fixtureToArray($fixture),
                $fixtures,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->components->info('Partidas normalizadas: '.count($fixtures));

        if ($fixtures === []) {
            return self::SUCCESS;
        }

        $this->table([
            'ID externo',
            'Competicao',
            'Rodada',
            'Data UTC',
            'Mandante',
            'Visitante',
            'Status',
            'Local',
        ], array_map(fn (FootballFixtureData $fixture): array => [
            $fixture->externalId,
            $fixture->competition->name,
            $fixture->round ?? '-',
            $fixture->startsAt->toDateTimeString(),
            $fixture->homeTeam->name,
            $fixture->awayTeam->name,
            $fixture->status,
            trim(($fixture->venue ?? '-').' / '.($fixture->city ?? '-')),
        ], array_slice($fixtures, 0, 20)));

        if (count($fixtures) > 20) {
            $this->components->warn('Exibindo as primeiras 20 partidas.');
        }

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

    /**
     * @return array<string, mixed>
     */
    private function fixtureToArray(FootballFixtureData $fixture): array
    {
        return [
            'provider' => $fixture->provider,
            'external_id' => $fixture->externalId,
            'competition' => [
                'external_id' => $fixture->competition->externalId,
                'name' => $fixture->competition->name,
                'country_code' => $fixture->competition->countryCode,
                'season_name' => $fixture->competition->seasonName,
            ],
            'home_team' => [
                'external_id' => $fixture->homeTeam->externalId,
                'name' => $fixture->homeTeam->name,
                'logo_url' => $fixture->homeTeam->logoUrl,
            ],
            'away_team' => [
                'external_id' => $fixture->awayTeam->externalId,
                'name' => $fixture->awayTeam->name,
                'logo_url' => $fixture->awayTeam->logoUrl,
            ],
            'round' => $fixture->round,
            'starts_at' => $fixture->startsAt->toIso8601String(),
            'status' => $fixture->status,
            'venue' => $fixture->venue,
            'city' => $fixture->city,
        ];
    }
}
