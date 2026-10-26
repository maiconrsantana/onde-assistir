<?php

namespace App\Filament\Resources\LlmUsageRecords\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LlmUsageRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i:s')->sortable(),
            TextColumn::make('provider')->label('Provedor')->badge(),
            TextColumn::make('model')->label('Modelo'),
            TextColumn::make('fixture.id')->label('Partida')->placeholder('-'),
            TextColumn::make('input_tokens')->label('Entrada'),
            TextColumn::make('output_tokens')->label('Saída'),
            TextColumn::make('total_tokens')->label('Total'),
            TextColumn::make('estimated_cost')->label('Custo')->formatStateUsing(fn ($state): string => 'US$ '.number_format((float) $state, 6, ',', '.'))->tooltip(fn ($state): string => 'Valor preciso: US$ '.number_format((float) $state, 8, ',', '.')),
            TextColumn::make('status')->label('Status')->badge(),
        ])->filters([
            SelectFilter::make('provider')->label('Provedor')->options(['openai' => 'OpenAI', 'gemini' => 'Gemini']),
            SelectFilter::make('status')->label('Status')->options(['success' => 'Sucesso', 'failed' => 'Falha']),
        ]);
    }
}
