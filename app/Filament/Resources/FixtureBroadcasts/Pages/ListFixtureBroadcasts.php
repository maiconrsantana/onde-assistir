<?php

namespace App\Filament\Resources\FixtureBroadcasts\Pages;

use App\Filament\Resources\FixtureBroadcasts\FixtureBroadcastResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFixtureBroadcasts extends ListRecords
{
    protected static string $resource = FixtureBroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
