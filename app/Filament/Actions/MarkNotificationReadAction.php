<?php

namespace App\Filament\Actions;

use App\Models\AppNotification;
use App\Services\NotificationService;
use Filament\Actions\Action as HeaderAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;

class MarkNotificationReadAction
{
    public static function make(): HeaderAction
    {
        return self::configure(HeaderAction::make('markNotificationRead'));
    }

    public static function makeTable(): TableAction
    {
        return self::configure(TableAction::make('markNotificationRead'))
            ->hiddenLabel()
            ->iconButton();
    }

    private static function configure(HeaderAction|TableAction $action): HeaderAction|TableAction
    {
        return $action
            ->label('Mark as read')
            ->icon('heroicon-o-check')
            ->color('success')
            ->visible(fn (AppNotification $record): bool => ! $record->is_read && $record->admin_id !== null)
            ->requiresConfirmation()
            ->action(function (AppNotification $record, NotificationService $notificationService): void {
                $notificationService->markAsReadForAdmin($record);

                Notification::make()
                    ->title('Notification marked as read')
                    ->success()
                    ->send();
            });
    }
}
