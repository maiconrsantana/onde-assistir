<?php

namespace App\Filament\Resources\FixtureBroadcasts\Pages;

use App\Filament\Resources\FixtureBroadcasts\FixtureBroadcastResource;
use App\Models\FixtureBroadcast;
use App\Services\Broadcast\ManualBroadcastRecorder;
use Filament\Resources\Pages\CreateRecord;

class CreateFixtureBroadcast extends CreateRecord
{
    protected static string $resource = FixtureBroadcastResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source_type'] = FixtureBroadcast::SOURCE_MANUAL;
        $data['verified_at'] ??= now()->utc();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(ManualBroadcastRecorder::class)->record($this->record);
    }
}
