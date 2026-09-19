<?php

namespace App\Filament\Resources\FootballFixtures\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FootballFixtureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('competition.name')
                    ->label('Competição'),
                TextEntry::make('round')
                    ->label('Rodada'),
                TextEntry::make('starts_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('homeTeam.name')
                    ->label('Mandante'),
                TextEntry::make('awayTeam.name')
                    ->label('Visitante'),
                TextEntry::make('venue')
                    ->label('Estádio'),
                TextEntry::make('city')
                    ->label('Cidade'),
                TextEntry::make('resolution_status')
                    ->label('Resolução')
                    ->badge(),
                TextEntry::make('review_status')
                    ->label('Revisão')
                    ->badge(),
                TextEntry::make('publication_status')
                    ->label('Publicação')
                    ->badge(),
            ]);
    }
}
