<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Support\OrderPresentation;
use App\Models\Payment;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $modelLabel = 'payment';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Payment details')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('order.order_number')
                            ->label('Order'),
                        Infolists\Components\TextEntry::make('order.customer_name')
                            ->label('Customer'),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->badge()
                            ->color(fn ($state): string => OrderPresentation::paymentMethodColor($state))
                            ->formatStateUsing(fn ($state): string => Str::headline($state->value)),
                        Infolists\Components\TextEntry::make('payment_status')
                            ->badge()
                            ->color(fn ($state): string => OrderPresentation::paymentStatusColor($state))
                            ->formatStateUsing(fn ($state): string => Str::headline($state->value)),
                        Infolists\Components\TextEntry::make('amount')
                            ->money('PKR'),
                        Infolists\Components\TextEntry::make('currency'),
                        Infolists\Components\TextEntry::make('transaction_id')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('gateway_reference')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('failure_reason')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('paid_at')
                            ->dateTime()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('failed_at')
                            ->dateTime()
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
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => Str::headline($state->value))
                    ->color(fn ($state): string => OrderPresentation::paymentMethodColor($state)),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => Str::headline($state->value))
                    ->color(fn ($state): string => OrderPresentation::paymentStatusColor($state)),
                Tables\Columns\TextColumn::make('amount')
                    ->money('PKR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gateway_reference')
                    ->label('Gateway ref')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('transaction_id')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options(collect(\App\Enums\PaymentStatus::cases())->mapWithKeys(
                        fn ($status): array => [$status->value => Str::headline($status->value)]
                    )->all()),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(collect(\App\Enums\PaymentMethod::cases())->mapWithKeys(
                        fn ($method): array => [$method->value => Str::headline($method->value)]
                    )->all()),
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
                                fn ($builder, string $date) => $builder->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn ($builder, string $date) => $builder->whereDate('created_at', '<=', $date),
                            );
                    }),
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
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['transaction_id', 'gateway_reference'];
    }
}
