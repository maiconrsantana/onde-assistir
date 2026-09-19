<?php

namespace App\Filament\Resources\Broadcasters\Pages;

use App\Filament\Resources\Broadcasters\BroadcasterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBroadcaster extends CreateRecord
{
    protected static string $resource = BroadcasterResource::class;
}
