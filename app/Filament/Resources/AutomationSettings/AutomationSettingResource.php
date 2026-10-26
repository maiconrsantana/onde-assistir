<?php

namespace App\Filament\Resources\AutomationSettings;

use App\Filament\Resources\AutomationSettings\Pages\EditAutomationSetting;
use App\Filament\Resources\AutomationSettings\Pages\ListAutomationSettings;
use App\Filament\Resources\AutomationSettings\Schemas\AutomationSettingForm;
use App\Filament\Resources\AutomationSettings\Tables\AutomationSettingsTable;
use App\Models\AutomationSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AutomationSettingResource extends Resource
{
    protected static ?string $model = AutomationSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Automações';

    protected static ?string $modelLabel = 'automação';

    protected static ?string $pluralModelLabel = 'automações';

    public static function form(Schema $schema): Schema
    {
        return AutomationSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutomationSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomationSettings::route('/'),
            'edit' => EditAutomationSetting::route('/{record}/edit'),
        ];
    }
}
