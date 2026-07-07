<?php

namespace App\Filament\Resources;

use App\Enums\InventoryLogType;
use App\Filament\Resources\InventoryLogResource\Pages;
use App\Models\InventoryLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class InventoryLogResource extends Resource
{
    protected static ?string $model = InventoryLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Inventory Logs';

    protected static ?string $modelLabel = 'inventory log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Adjustment details')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('product.name')
                            ->label('Product'),
                        Infolists\Components\TextEntry::make('product.sku_code')
                            ->label('SKU'),
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn (InventoryLogType $state): string => Str::headline($state->value)),
                        Infolists\Components\TextEntry::make('admin.name')
                            ->label('Adjusted by')
                            ->placeholder('System'),
                        Infolists\Components\TextEntry::make('old_quantity')
                            ->label('Old quantity'),
                        Infolists\Components\TextEntry::make('new_quantity')
                            ->label('New quantity'),
                        Infolists\Components\TextEntry::make('quantity_difference')
                            ->label('Change')
                            ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                            ->formatStateUsing(fn (int $state): string => $state > 0 ? "+{$state}" : (string) $state),
                        Infolists\Components\TextEntry::make('reference_type')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('reference_id')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('note')
                            ->columnSpanFull()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.sku_code')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (InventoryLogType $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('quantity_difference')
                    ->label('Change')
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "+{$state}" : (string) $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('old_quantity')
                    ->label('Old')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('new_quantity')
                    ->label('New')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('admin.name')
                    ->label('Admin')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('note')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(InventoryLogType::cases())->mapWithKeys(
                        fn (InventoryLogType $type): array => [$type->value => Str::headline($type->value)]
                    )->all()),
                Tables\Filters\SelectFilter::make('admin_id')
                    ->relationship('admin', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Admin'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryLogs::route('/'),
            'view' => Pages\ViewInventoryLog::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['note'];
    }
}
