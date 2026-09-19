<?php

namespace App\Filament\Resources\Broadcasters;

use App\Filament\Resources\Broadcasters\Pages\CreateBroadcaster;
use App\Filament\Resources\Broadcasters\Pages\EditBroadcaster;
use App\Filament\Resources\Broadcasters\Pages\ListBroadcasters;
use App\Filament\Resources\Broadcasters\Pages\ViewBroadcaster;
use App\Filament\Resources\Broadcasters\Schemas\BroadcasterForm;
use App\Filament\Resources\Broadcasters\Schemas\BroadcasterInfolist;
use App\Filament\Resources\Broadcasters\Tables\BroadcastersTable;
use App\Models\Broadcaster;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BroadcasterResource extends Resource
{
    protected static ?string $model = Broadcaster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTv;

    protected static ?string $navigationLabel = 'Emissoras';

    protected static ?string $modelLabel = 'emissora';

    protected static ?string $pluralModelLabel = 'emissoras';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BroadcasterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BroadcasterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BroadcastersTable::configure($table);
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
            'index' => ListBroadcasters::route('/'),
            'create' => CreateBroadcaster::route('/create'),
            'view' => ViewBroadcaster::route('/{record}'),
            'edit' => EditBroadcaster::route('/{record}/edit'),
        ];
    }
}
