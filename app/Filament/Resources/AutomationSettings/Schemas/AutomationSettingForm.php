<?php

namespace App\Filament\Resources\AutomationSettings\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AutomationSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->disabled(),
            Textarea::make('description')->label('Descrição')->disabled()->columnSpanFull(),
            Toggle::make('enabled')->label('Automação habilitada')->helperText('Quando desabilitada, novas execuções serão registradas como ignoradas.'),
            CheckboxList::make('schedule_days')->label('Dias da semana')->options([1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'])->columns(4)->helperText('Deixe vazio para executar somente manualmente.'),
            TimePicker::make('schedule_time')->label('Horário')->seconds(false)->helperText('Horário local de Brasília.'),
            TextInput::make('schedule')->label('Configuração legada')->disabled()->dehydrated(false),
        ]);
    }
}
