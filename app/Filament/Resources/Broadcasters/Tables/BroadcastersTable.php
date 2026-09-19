<?php

namespace App\Filament\Resources\Broadcasters\Tables;

use App\Models\Broadcaster;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BroadcastersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Broadcaster::TYPE_TV_OPEN => 'TV aberta',
                        Broadcaster::TYPE_TV_CLOSED => 'TV fechada',
                        Broadcaster::TYPE_STREAMING => 'Streaming',
                        Broadcaster::TYPE_YOUTUBE => 'YouTube',
                        default => 'Outro',
                    }),
                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        Broadcaster::TYPE_TV_OPEN => 'TV aberta',
                        Broadcaster::TYPE_TV_CLOSED => 'TV fechada',
                        Broadcaster::TYPE_STREAMING => 'Streaming',
                        Broadcaster::TYPE_YOUTUBE => 'YouTube',
                        Broadcaster::TYPE_OTHER => 'Outro',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
