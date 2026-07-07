<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Enums\InventoryLogType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class InventoryLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryLogs';

    protected static ?string $title = 'Stock history';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (InventoryLogType $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('quantity_difference')
                    ->label('Change')
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "+{$state}" : (string) $state),
                Tables\Columns\TextColumn::make('old_quantity')
                    ->label('Old'),
                Tables\Columns\TextColumn::make('new_quantity')
                    ->label('New'),
                Tables\Columns\TextColumn::make('admin.name')
                    ->label('Admin')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('note')
                    ->limit(40),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(InventoryLogType::cases())->mapWithKeys(
                        fn (InventoryLogType $type): array => [$type->value => Str::headline($type->value)]
                    )->all()),
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
