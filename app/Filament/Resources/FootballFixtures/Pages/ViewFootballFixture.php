<?php

namespace App\Filament\Resources\FootballFixtures\Pages;

use App\Filament\Resources\FootballFixtures\FootballFixtureResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFootballFixture extends ViewRecord
{
    protected static string $resource = FootballFixtureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
