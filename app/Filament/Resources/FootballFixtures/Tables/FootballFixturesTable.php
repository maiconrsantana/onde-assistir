<?php

namespace App\Filament\Resources\FootballFixtures\Tables;

use App\Enums\AutomationKey;
use App\Enums\AutomationTriggerType;
use App\Jobs\RunAutomationJob;
use App\Models\FootballFixture;
use App\Services\Operations\PublicScheduleCache;
use App\Services\Publication\FixturePublicationApprover;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FootballFixturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->timezone(config('app.timezone'))
                    ->sortable(),
                TextColumn::make('competition.name')
                    ->label('Competição')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('round')
                    ->label('Rodada')
                    ->searchable(),
                TextColumn::make('homeTeam.name')
                    ->label('Mandante')
                    ->searchable(),
                TextColumn::make('awayTeam.name')
                    ->label('Visitante')
                    ->searchable(),
                TextColumn::make('fixture_broadcasts_count')
                    ->label('Transmissões')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "Sim ({$state})" : 'Não')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('resolution_status')
                    ->label('Resolução')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        FootballFixture::RESOLUTION_RESOLVED => 'success',
                        FootballFixture::RESOLUTION_ERROR, FootballFixture::RESOLUTION_CONFLICTING => 'danger',
                        FootballFixture::RESOLUTION_UNCERTAIN, FootballFixture::RESOLUTION_NOT_FOUND => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('review_status')
                    ->label('Revisão')
                    ->badge()
                    ->color(fn (string $state): string => $state === FootballFixture::REVIEW_APPROVED ? 'success' : 'warning'),
                TextColumn::make('publication_status')
                    ->label('Publicação')
                    ->badge()
                    ->color(fn (string $state): string => $state === FootballFixture::PUBLICATION_PUBLISHED ? 'success' : 'gray'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('fixtureBroadcasts'))
            ->filters([
                SelectFilter::make('competition_id')
                    ->label('Competição')
                    ->relationship('competition', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('home_team_id')
                    ->label('Mandante')
                    ->relationship('homeTeam', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('away_team_id')
                    ->label('Visitante')
                    ->relationship('awayTeam', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('resolution_status')
                    ->label('Resolução')
                    ->options([
                        FootballFixture::RESOLUTION_PENDING => 'Pendente',
                        FootballFixture::RESOLUTION_RESOLVED => 'Resolvida',
                        FootballFixture::RESOLUTION_NOT_FOUND => 'Não encontrada',
                        FootballFixture::RESOLUTION_UNCERTAIN => 'Incerta',
                        FootballFixture::RESOLUTION_CONFLICTING => 'Conflitante',
                        FootballFixture::RESOLUTION_ERROR => 'Erro',
                    ]),
                SelectFilter::make('review_status')
                    ->label('Revisão')
                    ->options([
                        FootballFixture::REVIEW_PENDING => 'Pendente',
                        FootballFixture::REVIEW_APPROVED => 'Aprovada',
                        FootballFixture::REVIEW_REJECTED => 'Rejeitada',
                    ]),
                Filter::make('needs_review')
                    ->label('Com transmissão para revisar')
                    ->query(fn (Builder $query): Builder => $query->whereHas('fixtureBroadcasts', fn (Builder $broadcasts): Builder => $broadcasts->where('needs_review', true))),
                Filter::make('upcoming_without_broadcasts')
                    ->label('Próximas sem transmissão')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('starts_at', '>=', now()->utc())
                        ->whereDoesntHave('fixtureBroadcasts')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resolve_ai')
                    ->label('Buscar transmissão via IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (FootballFixture $record): void {
                        $provider = config('services.ai.broadcast_provider', 'gemini');
                        $key = $provider === 'openai' ? AutomationKey::ResolveOpenAIBroadcasts : AutomationKey::ResolveGeminiBroadcasts;
                        $date = $record->starts_at->timezone(config('app.timezone'))->toDateString();
                        RunAutomationJob::dispatch($key, ['--from' => $date, '--to' => $date, '--fixture-id' => $record->getKey(), '--limit' => 1, '--force' => true], auth()->id(), AutomationTriggerType::Manual);
                        Notification::make()->title('Busca de transmissão enfileirada')->body("Provedor: {$provider}. Você receberá uma notificação quando terminar.")->success()->send();
                    }),
                Action::make('approve_review')
                    ->label('Aprovar e publicar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FootballFixture $record): bool => $record->publication_status !== FootballFixture::PUBLICATION_PUBLISHED)
                    ->action(fn (FootballFixture $record, FixturePublicationApprover $approver) => $approver->approveAndPublish($record, auth()->user())),
                Action::make('unpublish')
                    ->label('Retirar do ar')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FootballFixture $record): bool => $record->publication_status === FootballFixture::PUBLICATION_PUBLISHED)
                    ->action(function (FootballFixture $record, PublicScheduleCache $cache): void {
                        $record->forceFill([
                            'publication_status' => FootballFixture::PUBLICATION_UNPUBLISHED,
                        ])->save();

                        $cache->invalidate();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('change_resolution_status')
                        ->label('Alterar resolução')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Select::make('resolution_status')
                                ->label('Novo status de resolução')
                                ->options([
                                    FootballFixture::RESOLUTION_PENDING => 'Pendente',
                                    FootballFixture::RESOLUTION_PROCESSING => 'Processando',
                                    FootballFixture::RESOLUTION_RESOLVED => 'Resolvida',
                                    FootballFixture::RESOLUTION_NOT_FOUND => 'Não encontrada',
                                    FootballFixture::RESOLUTION_UNCERTAIN => 'Incerta',
                                    FootballFixture::RESOLUTION_CONFLICTING => 'Conflitante',
                                    FootballFixture::RESOLUTION_ERROR => 'Erro',
                                ])
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->forceFill([
                                    'resolution_status' => $data['resolution_status'],
                                    'resolution_invalidated_at' => now()->utc(),
                                ])->save();
                            }

                            Notification::make()
                                ->title('Status atualizado')
                                ->body("{$records->count()} partida(s) atualizada(s).")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
