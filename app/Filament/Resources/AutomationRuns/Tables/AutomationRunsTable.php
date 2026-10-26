<?php

namespace App\Filament\Resources\AutomationRuns\Tables;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTriggerType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AutomationRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('automationSetting.name')->label('Automação')->searchable()->sortable(),
                TextColumn::make('trigger_type')->label('Disparo')->badge()->formatStateUsing(fn (AutomationTriggerType $state): string => $state === AutomationTriggerType::Manual ? 'Manual' : 'Agendado'),
                TextColumn::make('status')->label('Status')->badge()->color(fn (AutomationRunStatus $state): string => match ($state) {
                    AutomationRunStatus::Success => 'success',
                    AutomationRunStatus::Failed => 'danger',
                    AutomationRunStatus::Skipped => 'warning',
                    AutomationRunStatus::Running => 'info',
                }),
                TextColumn::make('started_at')->label('Início')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('duration_ms')->label('Duração')->formatStateUsing(fn (?int $state): string => $state === null ? '-' : "{$state} ms"),
                TextColumn::make('processed_count')->label('Processados'),
                TextColumn::make('failure_count')->label('Falhas'),
                TextColumn::make('user.name')->label('Usuário')->placeholder('Scheduler'),
                TextColumn::make('error_message')->label('Mensagem')->limit(60)->toggleable(),
                TextColumn::make('result.output')
                    ->label('Detalhes da chamada')
                    ->limit(120)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(collect(AutomationRunStatus::cases())->mapWithKeys(fn (AutomationRunStatus $status): array => [$status->value => ucfirst($status->value)])->all()),
                SelectFilter::make('trigger_type')->label('Disparo')->options([
                    AutomationTriggerType::Manual->value => 'Manual',
                    AutomationTriggerType::Scheduled->value => 'Agendado',
                ]),
            ]);
    }
}
