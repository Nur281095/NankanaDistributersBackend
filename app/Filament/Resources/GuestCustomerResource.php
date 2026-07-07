<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuestCustomerResource\Pages;
use App\Models\GuestCustomer;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GuestCustomerResource extends Resource
{
    protected static ?string $model = GuestCustomer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Guest Customers';

    protected static ?string $modelLabel = 'guest customer';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Guest details')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('phone'),
                        Infolists\Components\TextEntry::make('email')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('city')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('address')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('convertedUser.name')
                            ->label('Converted user')
                            ->placeholder('Not converted'),
                        Infolists\Components\TextEntry::make('orders_count')
                            ->label('Orders')
                            ->state(fn (GuestCustomer $record): int => $record->orders()->count()),
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label('Orders')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_orders')
                    ->label('Has orders')
                    ->query(fn ($query) => $query->has('orders')),
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
            'index' => Pages\ListGuestCustomers::route('/'),
            'view' => Pages\ViewGuestCustomer::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'email'];
    }
}
