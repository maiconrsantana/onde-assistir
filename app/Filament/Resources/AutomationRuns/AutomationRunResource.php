<?php

namespace App\Filament\Resources\AutomationRuns;

use App\Filament\Resources\AutomationRuns\Pages\ListAutomationRuns;
use App\Filament\Resources\AutomationRuns\Tables\AutomationRunsTable;
use App\Models\AutomationRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AutomationRunResource extends Resource
{
    protected static ?string $model = AutomationRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Logs das automações';

    protected static ?string $modelLabel = 'execução';

    protected static ?string $pluralModelLabel = 'execuções';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return AutomationRunsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListAutomationRuns::route('/')];
    }
}
