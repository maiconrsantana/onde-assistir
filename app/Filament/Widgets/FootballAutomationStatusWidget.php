<?php

namespace App\Filament\Widgets;

use App\Services\Operations\FootballAutomationStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class FootballAutomationStatusWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Status das automações';

    protected ?string $description = 'Atualização dos dados esportivos e das transmissões.';

    protected function getStats(): array
    {
        $status = app(FootballAutomationStatus::class);

        return collect([
            'football:sync' => 'Partidas',
            'football:resolve-broadcasts' => 'TheSportsDB',
            'football:resolve-openai-broadcasts' => 'OpenAI fallback',
            'football:refresh-broadcasts' => 'Atualização geral',
        ])->map(function (string $label, string $command) use ($status): Stat {
            $row = $status->statusFor($command);
            $fresh = $status->isFresh($command);
            $lastSuccessAt = $row['last_success_at']
                ? Carbon::parse($row['last_success_at'])
                    ->timezone(config('app.timezone'))
                    ->format('d/m/Y H:i')
                : 'nunca';

            return Stat::make($label, $fresh ? 'Atualizado' : 'Atenção')
                ->description("Último sucesso: {$lastSuccessAt}")
                ->descriptionIcon($fresh ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($fresh ? 'success' : 'warning');
        })->values()->all();
    }
}
