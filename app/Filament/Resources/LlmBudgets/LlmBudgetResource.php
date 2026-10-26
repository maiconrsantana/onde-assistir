<?php

namespace App\Filament\Resources\LlmBudgets;

use App\Filament\Resources\LlmBudgets\Pages\EditLlmBudget;
use App\Filament\Resources\LlmBudgets\Pages\ListLlmBudgets;
use App\Filament\Resources\LlmBudgets\Schemas\LlmBudgetForm;
use App\Filament\Resources\LlmBudgets\Tables\LlmBudgetsTable;
use App\Models\LlmBudget;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LlmBudgetResource extends Resource
{
    protected static ?string $model = LlmBudget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Orçamentos de IA';

    protected static ?string $modelLabel = 'orçamento';

    protected static ?string $pluralModelLabel = 'orçamentos';

    public static function form(Schema $schema): Schema
    {
        return LlmBudgetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LlmBudgetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLlmBudgets::route('/'),
            'edit' => EditLlmBudget::route('/{record}/edit'),
        ];
    }
}
