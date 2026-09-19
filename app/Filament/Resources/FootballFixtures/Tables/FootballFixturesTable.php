<?php

namespace App\Filament\Resources\FootballFixtures\Tables;

use App\Models\FootballFixture;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class FootballFixturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
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
                Action::make('approve_review')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (FootballFixture $record): void {
                        $record->fixtureBroadcasts()->update(['needs_review' => false]);
                        $record->forceFill([
                            'review_status' => FootballFixture::REVIEW_APPROVED,
                            'approved_by' => Auth::id(),
                            'approved_at' => now()->utc(),
                        ])->save();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
