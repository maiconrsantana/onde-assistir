<?php

namespace App\Filament\Resources\FixtureBroadcasts\Tables;

use App\Models\FixtureBroadcast;
use App\Services\Operations\PublicScheduleCache;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FixtureBroadcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fixture.starts_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->timezone(config('app.timezone'))
                    ->sortable(),
                TextColumn::make('fixture.homeTeam.name')
                    ->label('Mandante')
                    ->searchable(),
                TextColumn::make('fixture.awayTeam.name')
                    ->label('Visitante')
                    ->searchable(),
                TextColumn::make('broadcaster.name')
                    ->label('Emissora')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('access_type')
                    ->label('Acesso')
                    ->badge(),
                TextColumn::make('source_type')
                    ->label('Origem')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        FixtureBroadcast::SOURCE_MANUAL => 'success',
                        FixtureBroadcast::SOURCE_OPENAI => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('needs_review')
                    ->label('Revisar')
                    ->boolean(),
                TextColumn::make('review_status')
                    ->label('Aprovado')
                    ->badge(),
                TextColumn::make('publication_status')
                    ->label('Publicado')
                    ->badge(),
                TextColumn::make('confidence')
                    ->label('Confiança')
                    ->numeric(decimalPlaces: 2),
            ])
            ->filters([
                SelectFilter::make('broadcaster_id')
                    ->label('Emissora')
                    ->relationship('broadcaster', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('source_type')
                    ->label('Origem')
                    ->options([
                        FixtureBroadcast::SOURCE_THESPORTSDB => 'TheSportsDB',
                        FixtureBroadcast::SOURCE_OPENAI => 'OpenAI',
                        FixtureBroadcast::SOURCE_MANUAL => 'Manual',
                    ]),
                Filter::make('needs_review')
                    ->label('Precisa de revisão')
                    ->query(fn (Builder $query): Builder => $query->where('needs_review', true)),
                Filter::make('missing_source')
                    ->label('Sem fonte')
                    ->query(fn (Builder $query): Builder => $query->whereNull('source_url')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Aprovar e publicar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FixtureBroadcast $record): bool => $record->publication_status !== FixtureBroadcast::PUBLICATION_PUBLISHED)
                    ->action(function (FixtureBroadcast $record, PublicScheduleCache $cache): void {
                        $record->forceFill([
                            'needs_review' => false,
                            'review_status' => FixtureBroadcast::REVIEW_APPROVED,
                            'publication_status' => FixtureBroadcast::PUBLICATION_PUBLISHED,
                            'verified_at' => now()->utc(),
                        ])->save();

                        $cache->invalidate();
                    }),
                Action::make('unpublish')
                    ->label('Retirar do ar')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FixtureBroadcast $record): bool => $record->publication_status === FixtureBroadcast::PUBLICATION_PUBLISHED)
                    ->action(function (FixtureBroadcast $record, PublicScheduleCache $cache): void {
                        $record->forceFill([
                            'publication_status' => FixtureBroadcast::PUBLICATION_UNPUBLISHED,
                        ])->save();

                        $cache->invalidate();
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
