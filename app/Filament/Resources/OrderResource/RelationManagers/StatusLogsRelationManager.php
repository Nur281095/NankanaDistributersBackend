<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Enums\ChangedByType;
use App\Enums\OrderStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class StatusLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusLogs';

    protected static ?string $title = 'Status timeline';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('old_status')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?OrderStatus $state): string => $state ? Str::headline($state->value) : '—'),
                Tables\Columns\TextColumn::make('new_status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('changed_by_type')
                    ->label('Changed by')
                    ->formatStateUsing(fn (ChangedByType $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('note')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
