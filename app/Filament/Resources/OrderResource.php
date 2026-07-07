<?php

namespace App\Filament\Resources;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Filament\Support\OrderPresentation;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $modelLabel = 'order';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Internal notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin note')
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Order summary')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('order_number'),
                        Infolists\Components\TextEntry::make('order_status')
                            ->badge()
                            ->color(fn (OrderStatus $state): string => OrderPresentation::orderStatusColor($state))
                            ->formatStateUsing(fn (OrderStatus $state): string => Str::headline($state->value)),
                        Infolists\Components\TextEntry::make('payment_status')
                            ->badge()
                            ->color(fn (OrderPaymentStatus $state): string => OrderPresentation::orderPaymentStatusColor($state))
                            ->formatStateUsing(fn (OrderPaymentStatus $state): string => Str::headline($state->value)),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->badge()
                            ->color(fn (PaymentMethod $state): string => OrderPresentation::paymentMethodColor($state))
                            ->formatStateUsing(fn (PaymentMethod $state): string => Str::headline($state->value)),
                        Infolists\Components\IconEntry::make('is_guest')
                            ->label('Guest order')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('cancellation_deadline')
                            ->dateTime()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('delivered_at')
                            ->dateTime()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('cancelled_at')
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
                Infolists\Components\Section::make('Customer')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('customer_name'),
                        Infolists\Components\TextEntry::make('customer_phone'),
                        Infolists\Components\TextEntry::make('customer_email')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Registered user')
                            ->placeholder('Guest checkout'),
                        Infolists\Components\TextEntry::make('guestCustomer.name')
                            ->label('Guest profile')
                            ->placeholder('—'),
                    ]),
                Infolists\Components\Section::make('Delivery')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('delivery_address')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('city')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('area')
                            ->placeholder('—'),
                    ]),
                Infolists\Components\Section::make('Totals')
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->money('PKR'),
                        Infolists\Components\TextEntry::make('delivery_charges')
                            ->money('PKR'),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->money('PKR'),
                        Infolists\Components\TextEntry::make('grand_total')
                            ->money('PKR')
                            ->weight('bold'),
                    ]),
                Infolists\Components\Section::make('Notes')
                    ->columns(1)
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Customer note')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('admin_note')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('order_status')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => OrderPresentation::orderStatusColor($state))
                    ->formatStateUsing(fn (OrderStatus $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (OrderPaymentStatus $state): string => OrderPresentation::orderPaymentStatusColor($state))
                    ->formatStateUsing(fn (OrderPaymentStatus $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn (PaymentMethod $state): string => Str::headline($state->value))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('grand_total')
                    ->money('PKR')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_guest')
                    ->label('Guest')
                    ->boolean(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('order_status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(
                        fn (OrderStatus $status): array => [$status->value => Str::headline($status->value)]
                    )->all()),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options(collect(OrderPaymentStatus::cases())->mapWithKeys(
                        fn (OrderPaymentStatus $status): array => [$status->value => Str::headline($status->value)]
                    )->all()),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(
                        fn (PaymentMethod $method): array => [$method->value => Str::headline($method->value)]
                    )->all()),
                Tables\Filters\TernaryFilter::make('is_guest')
                    ->label('Guest checkout'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn ($builder, $date) => $builder->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn ($builder, $date) => $builder->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->label('Admin note'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\StatusLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number', 'customer_name', 'customer_phone', 'customer_email'];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(\App\Services\DashboardService::class)->activeOrdersCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Active orders needing attention';
    }
}
