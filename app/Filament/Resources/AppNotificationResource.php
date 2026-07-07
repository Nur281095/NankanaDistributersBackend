<?php

namespace App\Filament\Resources;

use App\Enums\NotificationType;
use App\Filament\Actions\MarkNotificationReadAction;
use App\Filament\Resources\AppNotificationResource\Pages;
use App\Filament\Support\NotificationPresentation;
use App\Models\AppNotification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AppNotificationResource extends Resource
{
    protected static ?string $model = AppNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $modelLabel = 'notification';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Notification')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('title'),
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->color(fn (NotificationType $state): string => NotificationPresentation::typeColor($state))
                            ->formatStateUsing(fn (NotificationType $state): string => Str::headline($state->value)),
                        Infolists\Components\IconEntry::make('is_read')
                            ->label('Read')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('read_at')
                            ->dateTime()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Customer recipient')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('admin.name')
                            ->label('Admin recipient')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('reference_type')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('reference_id')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('message')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('data')
                            ->label('Payload')
                            ->formatStateUsing(fn (?array $state): string => $state === null
                                ? '—'
                                : json_encode($state, JSON_PRETTY_PRINT))
                            ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (NotificationType $state): string => NotificationPresentation::typeColor($state))
                    ->formatStateUsing(fn (NotificationType $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('admin.name')
                    ->label('Admin')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_read')
                    ->label('Read')
                    ->boolean(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(NotificationType::cases())->mapWithKeys(
                        fn (NotificationType $type): array => [$type->value => Str::headline($type->value)]
                    )->all()),
                Tables\Filters\TernaryFilter::make('is_read')
                    ->label('Read status'),
                Tables\Filters\Filter::make('customer_notifications')
                    ->label('Customer notifications')
                    ->query(fn ($query) => $query->whereNotNull('user_id')),
                Tables\Filters\Filter::make('admin_notifications')
                    ->label('Admin notifications')
                    ->query(fn ($query) => $query->whereNotNull('admin_id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                MarkNotificationReadAction::makeTable(),
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
            'index' => Pages\ListAppNotifications::route('/'),
            'view' => Pages\ViewAppNotification::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'message'];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(\App\Services\DashboardService::class)->unreadAdminNotificationsCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Unread admin notifications';
    }
}
