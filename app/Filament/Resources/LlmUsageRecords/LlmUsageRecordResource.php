<?php

namespace App\Filament\Resources\LlmUsageRecords;

use App\Filament\Resources\LlmUsageRecords\Pages\ListLlmUsageRecords;
use App\Filament\Resources\LlmUsageRecords\Tables\LlmUsageRecordsTable;
use App\Models\LlmUsageRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LlmUsageRecordResource extends Resource
{
    protected static ?string $model = LlmUsageRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Consumo de IA';

    protected static ?string $modelLabel = 'registro de consumo';

    protected static ?string $pluralModelLabel = 'consumos de IA';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return LlmUsageRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListLlmUsageRecords::route('/')];
    }
}
