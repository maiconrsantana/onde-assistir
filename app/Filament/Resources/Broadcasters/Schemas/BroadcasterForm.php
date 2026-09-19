<?php

namespace App\Filament\Resources\Broadcasters\Schemas;

use App\Models\Broadcaster;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BroadcasterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('type')
                    ->label('Tipo')
                    ->required()
                    ->in([
                        Broadcaster::TYPE_TV_OPEN,
                        Broadcaster::TYPE_TV_CLOSED,
                        Broadcaster::TYPE_STREAMING,
                        Broadcaster::TYPE_YOUTUBE,
                        Broadcaster::TYPE_OTHER,
                    ])
                    ->options([
                        Broadcaster::TYPE_TV_OPEN => 'TV aberta',
                        Broadcaster::TYPE_TV_CLOSED => 'TV fechada',
                        Broadcaster::TYPE_STREAMING => 'Streaming',
                        Broadcaster::TYPE_YOUTUBE => 'YouTube',
                        Broadcaster::TYPE_OTHER => 'Outro',
                    ]),
            ]);
    }
}
