<?php

namespace App\Filament\Resources\Broadcasters\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BroadcasterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nome'),
                TextEntry::make('slug')
                    ->label('Slug'),
                TextEntry::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextEntry::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}
