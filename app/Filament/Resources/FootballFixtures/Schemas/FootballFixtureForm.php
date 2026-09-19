<?php

namespace App\Filament\Resources\FootballFixtures\Schemas;

use App\Models\FootballFixture;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FootballFixtureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('competition_id')
                    ->label('Competição')
                    ->relationship('competition', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('home_team_id')
                    ->label('Mandante')
                    ->relationship('homeTeam', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('away_team_id')
                    ->label('Visitante')
                    ->relationship('awayTeam', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('round')
                    ->label('Rodada')
                    ->maxLength(255),
                DateTimePicker::make('starts_at')
                    ->label('Data e hora')
                    ->timezone(config('app.timezone'))
                    ->seconds(false)
                    ->required(),
                TextInput::make('venue')
                    ->label('Estádio')
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Cidade')
                    ->maxLength(255),
                Select::make('resolution_status')
                    ->label('Resolução')
                    ->required()
                    ->options([
                        FootballFixture::RESOLUTION_PENDING => 'Pendente',
                        FootballFixture::RESOLUTION_PROCESSING => 'Processando',
                        FootballFixture::RESOLUTION_RESOLVED => 'Resolvida',
                        FootballFixture::RESOLUTION_NOT_FOUND => 'Não encontrada',
                        FootballFixture::RESOLUTION_UNCERTAIN => 'Incerta',
                        FootballFixture::RESOLUTION_CONFLICTING => 'Conflitante',
                        FootballFixture::RESOLUTION_ERROR => 'Erro',
                    ]),
                Select::make('review_status')
                    ->label('Revisão')
                    ->required()
                    ->options([
                        FootballFixture::REVIEW_PENDING => 'Pendente',
                        FootballFixture::REVIEW_APPROVED => 'Aprovada',
                        FootballFixture::REVIEW_REJECTED => 'Rejeitada',
                    ]),
                Select::make('publication_status')
                    ->label('Publicação')
                    ->required()
                    ->options([
                        FootballFixture::PUBLICATION_DRAFT => 'Rascunho',
                        FootballFixture::PUBLICATION_PUBLISHED => 'Publicado',
                        FootballFixture::PUBLICATION_UNPUBLISHED => 'Retirado do ar',
                    ]),
            ]);
    }
}
