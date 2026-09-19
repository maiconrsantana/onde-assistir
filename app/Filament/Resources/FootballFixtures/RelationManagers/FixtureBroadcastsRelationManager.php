<?php

namespace App\Filament\Resources\FootballFixtures\RelationManagers;

use App\Models\FixtureBroadcast;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FixtureBroadcastsRelationManager extends RelationManager
{
    protected static string $relationship = 'fixtureBroadcasts';

    protected static ?string $title = 'Transmissões vinculadas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('broadcaster.name')
                    ->label('Emissora')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country_code')
                    ->label('País'),
                TextColumn::make('access_type')
                    ->label('Acesso')
                    ->badge(),
                IconColumn::make('needs_review')
                    ->label('Revisar')
                    ->boolean(),
                TextColumn::make('review_status')
                    ->label('Aprovado')
                    ->badge()
                    ->color(fn (string $state): string => $state === FixtureBroadcast::REVIEW_APPROVED ? 'success' : 'warning'),
                TextColumn::make('publication_status')
                    ->label('Publicado')
                    ->badge()
                    ->color(fn (string $state): string => $state === FixtureBroadcast::PUBLICATION_PUBLISHED ? 'success' : 'gray'),
                TextColumn::make('verified_at')
                    ->label('Verificado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
