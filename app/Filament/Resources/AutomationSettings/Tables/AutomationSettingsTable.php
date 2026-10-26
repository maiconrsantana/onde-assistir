<?php

namespace App\Filament\Resources\AutomationSettings\Tables;

use App\Enums\AutomationKey;
use App\Enums\AutomationTriggerType;
use App\Jobs\RunAutomationJob;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AutomationSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Automação')->searchable()->sortable(),
                TextColumn::make('schedule_time')->label('Agendamento')->formatStateUsing(fn ($state, $record): string => $state ? collect($record->schedule_days ?? [])->map(fn (int $day): string => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'][$day - 1] ?? '?')->join(', ')." {$state}" : 'Manual'),
                IconColumn::make('enabled')->label('Ativa')->boolean(),
                TextColumn::make('last_run_at')->label('Última execução')->dateTime('d/m/Y H:i')->sortable()->placeholder('Nunca'),
                TextColumn::make('runs_count')->label('Execuções')->counts('runs'),
            ])
            ->recordActions([
                Action::make('run')
                    ->label('Executar agora')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        RunAutomationJob::dispatch(AutomationKey::from($record->key), [], auth()->id(), AutomationTriggerType::Manual);
                        Notification::make()->title('Automação enfileirada')->body('O worker executará a chamada e você receberá uma notificação ao finalizar.')->success()->send();
                    }),
                EditAction::make(),
            ]);
    }
}
