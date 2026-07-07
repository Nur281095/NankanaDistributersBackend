<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Filament\Resources\PaymentResource;
use App\Filament\Support\OrderPresentation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => Str::headline($state->value))
                    ->color(fn ($state): string => OrderPresentation::paymentMethodColor($state)),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => Str::headline($state->value))
                    ->color(fn ($state): string => OrderPresentation::paymentStatusColor($state)),
                Tables\Columns\TextColumn::make('amount')
                    ->money('PKR'),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('transaction_id')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record): string => PaymentResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
