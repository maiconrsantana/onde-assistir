<?php

namespace App\Filament\Resources\FixtureBroadcasts\Pages;

use App\Filament\Resources\FixtureBroadcasts\FixtureBroadcastResource;
use App\Models\FixtureBroadcast;
use App\Services\Broadcast\ManualBroadcastRecorder;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFixtureBroadcast extends EditRecord
{
    protected static string $resource = FixtureBroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['source_type'] = FixtureBroadcast::SOURCE_MANUAL;
        $data['verified_at'] ??= now()->utc();

        return $data;
    }

    protected function afterSave(): void
    {
        app(ManualBroadcastRecorder::class)->record($this->record);
    }
}
