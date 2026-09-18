<?php

namespace App\Console\Commands;

use App\Services\Operations\FootballAutomationStatus;
use Illuminate\Console\Command;

class AutomationStatusCommand extends Command
{
    protected $signature = 'football:automation-status';

    protected $description = 'Show last success and failure timestamps for football automations.';

    public function handle(FootballAutomationStatus $status): int
    {
        $this->table([
            'Comando',
            'Status',
            'Ultimo sucesso',
            'Ultima falha',
            'Mensagem',
        ], collect($status->all())
            ->map(fn (array $row): array => [
                $row['command'],
                $row['last_status'],
                $row['last_success_at'] ?? '-',
                $row['last_failure_at'] ?? '-',
                $row['message'] ?? '-',
            ])
            ->all());

        return self::SUCCESS;
    }
}
