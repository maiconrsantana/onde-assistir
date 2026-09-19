<?php

namespace App\Filament\Resources\FootballFixtures\Pages;

use App\Filament\Resources\FootballFixtures\FootballFixtureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFootballFixtures extends ListRecords
{
    protected static string $resource = FootballFixtureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
