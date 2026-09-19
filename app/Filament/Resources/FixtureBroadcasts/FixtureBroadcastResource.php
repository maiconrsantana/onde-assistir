<?php

namespace App\Filament\Resources\FixtureBroadcasts;

use App\Filament\Resources\FixtureBroadcasts\Pages\CreateFixtureBroadcast;
use App\Filament\Resources\FixtureBroadcasts\Pages\EditFixtureBroadcast;
use App\Filament\Resources\FixtureBroadcasts\Pages\ListFixtureBroadcasts;
use App\Filament\Resources\FixtureBroadcasts\Pages\ViewFixtureBroadcast;
use App\Filament\Resources\FixtureBroadcasts\Schemas\FixtureBroadcastForm;
use App\Filament\Resources\FixtureBroadcasts\Schemas\FixtureBroadcastInfolist;
use App\Filament\Resources\FixtureBroadcasts\Tables\FixtureBroadcastsTable;
use App\Models\FixtureBroadcast;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FixtureBroadcastResource extends Resource
{
    protected static ?string $model = FixtureBroadcast::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Transmissões';

    protected static ?string $modelLabel = 'transmissão';

    protected static ?string $pluralModelLabel = 'transmissões';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return FixtureBroadcastForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FixtureBroadcastInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FixtureBroadcastsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFixtureBroadcasts::route('/'),
            'create' => CreateFixtureBroadcast::route('/create'),
            'view' => ViewFixtureBroadcast::route('/{record}'),
            'edit' => EditFixtureBroadcast::route('/{record}/edit'),
        ];
    }
}
