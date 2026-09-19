<?php

namespace App\Filament\Resources\FootballFixtures;

use App\Filament\Resources\FootballFixtures\Pages\CreateFootballFixture;
use App\Filament\Resources\FootballFixtures\Pages\EditFootballFixture;
use App\Filament\Resources\FootballFixtures\Pages\ListFootballFixtures;
use App\Filament\Resources\FootballFixtures\Pages\ViewFootballFixture;
use App\Filament\Resources\FootballFixtures\RelationManagers\FixtureBroadcastsRelationManager;
use App\Filament\Resources\FootballFixtures\Schemas\FootballFixtureForm;
use App\Filament\Resources\FootballFixtures\Schemas\FootballFixtureInfolist;
use App\Filament\Resources\FootballFixtures\Tables\FootballFixturesTable;
use App\Models\FootballFixture;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FootballFixtureResource extends Resource
{
    protected static ?string $model = FootballFixture::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Partidas';

    protected static ?string $modelLabel = 'partida';

    protected static ?string $pluralModelLabel = 'partidas';

    protected static ?string $recordTitleAttribute = 'external_id';

    public static function form(Schema $schema): Schema
    {
        return FootballFixtureForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FootballFixtureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FootballFixturesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FixtureBroadcastsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFootballFixtures::route('/'),
            'create' => CreateFootballFixture::route('/create'),
            'view' => ViewFootballFixture::route('/{record}'),
            'edit' => EditFootballFixture::route('/{record}/edit'),
        ];
    }
}
