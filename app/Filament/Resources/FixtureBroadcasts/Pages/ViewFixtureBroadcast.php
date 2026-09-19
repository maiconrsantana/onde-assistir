<?php

namespace App\Filament\Resources\FixtureBroadcasts\Pages;

use App\Filament\Resources\FixtureBroadcasts\FixtureBroadcastResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFixtureBroadcast extends ViewRecord
{
    protected static string $resource = FixtureBroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
