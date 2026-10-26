<?php

namespace App\Filament\Resources\LlmBudgets\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LlmBudgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('provider')->label('Provedor')->disabled()->dehydrated(false),
            Toggle::make('enabled')->label('Permitir chamadas'),
            TextInput::make('daily_limit')->label('Limite diário (USD)')->numeric()->nullable(),
            TextInput::make('monthly_limit')->label('Limite mensal (USD)')->numeric()->nullable(),
            TextInput::make('max_per_request')->label('Máximo por chamada (USD)')->numeric()->nullable()
                ->helperText('Usado como reserva preventiva antes de chamar a API.'),
        ]);
    }
}
