<?php

namespace App\Filament\Resources\LlmBudgets\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LlmBudgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('provider')->label('Provedor')->badge(),
            IconColumn::make('enabled')->label('Ativo')->boolean(),
            TextColumn::make('daily_limit')->label('Diário')->money('USD')->placeholder('Sem limite'),
            TextColumn::make('monthly_limit')->label('Mensal')->money('USD')->placeholder('Sem limite'),
            TextColumn::make('max_per_request')->label('Por chamada')->money('USD')->placeholder('Sem reserva'),
        ])->recordActions([EditAction::make()]);
    }
}
