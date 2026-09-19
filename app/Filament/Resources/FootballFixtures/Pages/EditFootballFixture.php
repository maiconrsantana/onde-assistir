<?php

namespace App\Filament\Resources\FootballFixtures\Pages;

use App\Filament\Resources\FootballFixtures\FootballFixtureResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFootballFixture extends EditRecord
{
    protected static string $resource = FootballFixtureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
