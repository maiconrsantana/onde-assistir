<?php

namespace App\Console\Commands;

use App\Services\Operations\FootballAutomationStatus;
use App\Services\Operations\PublicScheduleCache;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RefreshBroadcastsCommand extends Command
{
    protected $signature = 'football:refresh-broadcasts
        {--from= : Start date in YYYY-MM-DD format}
        {--to= : End date in YYYY-MM-DD format}
        {--days=7 : Number of days to include when --to is not provided}
        {--sportsdb-limit=50 : Maximum number of TheSportsDB fixtures}
        {--openai-limit=10 : Maximum number of OpenAI fallback fixtures}';

    protected $description = 'Run TheSportsDB broadcast resolution followed by OpenAI fallback.';

    public function handle(FootballAutomationStatus $status, PublicScheduleCache $cache): int
    {
        [$from, $to] = $this->dateRange();

        $baseArguments = [
            '--from' => $from->toDateString(),
            '--to' => $to->toDateString(),
        ];

        $sportsDbExitCode = $this->call('football:resolve-broadcasts', $baseArguments + [
            '--limit' => max(1, (int) $this->option('sportsdb-limit')),
        ]);

        $openAiExitCode = $this->call('football:resolve-openai-broadcasts', $baseArguments + [
            '--limit' => max(1, (int) $this->option('openai-limit')),
        ]);

        if ($sportsDbExitCode === self::SUCCESS && $openAiExitCode === self::SUCCESS) {
            $cache->invalidate();
            $status->recordSuccess('football:refresh-broadcasts', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);

            return self::SUCCESS;
        }

        $status->recordFailure('football:refresh-broadcasts', 'Uma ou mais etapas de transmissao falharam.', [
            'sportsdb_exit_code' => $sportsDbExitCode,
            'openai_exit_code' => $openAiExitCode,
        ]);

        return self::FAILURE;
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
