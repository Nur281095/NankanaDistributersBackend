<?php

namespace App\Filament\Resources;

use App\Enums\EmailLogStatus;
use App\Filament\Resources\EmailLogResource\Pages;
use App\Filament\Support\EmailLogPresentation;
use App\Models\EmailLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Email Logs';

    protected static ?string $modelLabel = 'email log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Delivery details')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('recipient'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (EmailLogStatus $state): string => EmailLogPresentation::statusColor($state))
                            ->formatStateUsing(fn (EmailLogStatus $state): string => Str::headline($state->value)),
                        Infolists\Components\TextEntry::make('subject')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('sent_at')
                            ->dateTime()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('reference_type')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('reference_id')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Error')
                            ->color('danger')
                            ->columnSpanFull()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('body')
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
                Tables\Columns\TextColumn::make('recipient')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (EmailLogStatus $state): string => EmailLogPresentation::statusColor($state))
                    ->formatStateUsing(fn (EmailLogStatus $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reference_type')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reference_id')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(EmailLogStatus::cases())->mapWithKeys(
                        fn (EmailLogStatus $status): array => [$status->value => Str::headline($status->value)]
                    )->all()),
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
            'index' => Pages\ListEmailLogs::route('/'),
            'view' => Pages\ViewEmailLog::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['recipient', 'subject'];
    }
}
