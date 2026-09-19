<?php

namespace App\Filament\Resources\FixtureBroadcasts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FixtureBroadcastInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('fixture.starts_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('fixture.homeTeam.name')
                    ->label('Mandante'),
                TextEntry::make('fixture.awayTeam.name')
                    ->label('Visitante'),
                TextEntry::make('broadcaster.name')
                    ->label('Emissora'),
                TextEntry::make('access_type')
                    ->label('Acesso')
                    ->badge(),
                TextEntry::make('source_type')
                    ->label('Origem')
                    ->badge(),
                TextEntry::make('source_url')
                    ->label('Fonte')
                    ->url(fn (?string $state): ?string => $state),
                TextEntry::make('confidence')
                    ->label('Confiança')
                    ->numeric(decimalPlaces: 2),
                IconEntry::make('needs_review')
                    ->label('Precisa de revisão')
                    ->boolean(),
                TextEntry::make('notes')
                    ->label('Observações'),
            ]);
    }
}
