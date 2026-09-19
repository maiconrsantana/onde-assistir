<?php

namespace App\Filament\Resources\FixtureBroadcasts\Schemas;

use App\Models\FixtureBroadcast;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FixtureBroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('football_fixture_id')
                    ->label('Partida')
                    ->relationship('fixture', 'external_id')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->homeTeam?->name} x {$record->awayTeam?->name} - {$record->starts_at?->format('d/m/Y H:i')}")
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('broadcaster_id')
                    ->label('Emissora')
                    ->relationship('broadcaster', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('access_type')
                    ->label('Acesso')
                    ->required()
                    ->in([
                        FixtureBroadcast::ACCESS_FREE,
                        FixtureBroadcast::ACCESS_SUBSCRIPTION,
                        FixtureBroadcast::ACCESS_PAY_PER_VIEW,
                        FixtureBroadcast::ACCESS_UNKNOWN,
                    ])
                    ->options([
                        FixtureBroadcast::ACCESS_FREE => 'Gratuito',
                        FixtureBroadcast::ACCESS_SUBSCRIPTION => 'Assinatura',
                        FixtureBroadcast::ACCESS_PAY_PER_VIEW => 'Pay-per-view',
                        FixtureBroadcast::ACCESS_UNKNOWN => 'Desconhecido',
                    ]),
                Select::make('source_type')
                    ->label('Origem')
                    ->required()
                    ->in([
                        FixtureBroadcast::SOURCE_THESPORTSDB,
                        FixtureBroadcast::SOURCE_OPENAI,
                        FixtureBroadcast::SOURCE_MANUAL,
                    ])
                    ->options([
                        FixtureBroadcast::SOURCE_THESPORTSDB => 'TheSportsDB',
                        FixtureBroadcast::SOURCE_OPENAI => 'OpenAI',
                        FixtureBroadcast::SOURCE_MANUAL => 'Manual',
                    ])
                    ->default(FixtureBroadcast::SOURCE_MANUAL),
                TextInput::make('country_code')
                    ->label('País')
                    ->required()
                    ->maxLength(2)
                    ->default('BR'),
                TextInput::make('source_url')
                    ->label('Fonte')
                    ->url()
                    ->maxLength(255),
                TextInput::make('confidence')
                    ->label('Confiança')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1)
                    ->step(0.0001),
                DateTimePicker::make('verified_at')
                    ->label('Verificado em')
                    ->seconds(false),
                Toggle::make('needs_review')
                    ->label('Precisa de revisão'),
                Select::make('review_status')
                    ->label('Aprovado')
                    ->required()
                    ->default(FixtureBroadcast::REVIEW_PENDING)
                    ->options([
                        FixtureBroadcast::REVIEW_PENDING => 'Pendente',
                        FixtureBroadcast::REVIEW_APPROVED => 'Aprovado',
                        FixtureBroadcast::REVIEW_REJECTED => 'Rejeitado',
                    ]),
                Select::make('publication_status')
                    ->label('Publicado')
                    ->required()
                    ->default(FixtureBroadcast::PUBLICATION_DRAFT)
                    ->options([
                        FixtureBroadcast::PUBLICATION_DRAFT => 'Rascunho',
                        FixtureBroadcast::PUBLICATION_PUBLISHED => 'Publicado',
                        FixtureBroadcast::PUBLICATION_UNPUBLISHED => 'Retirado do ar',
                    ]),
                Textarea::make('notes')
                    ->label('Observações')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }
}
