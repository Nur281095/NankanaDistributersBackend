<?php

namespace App\Filament\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

class CancelOrderAction
{
    public static function make(): Action
    {
        return Action::make('cancelOrder')
            ->label('Cancel order')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel this order?')
            ->modalDescription('Stock will be restored and payment records marked as refunded.')
            ->visible(fn (Order $record): bool => in_array($record->order_status, [
                OrderStatus::Received,
                OrderStatus::Packed,
                OrderStatus::OnWay,
            ], true))
            ->form([
                Forms\Components\Textarea::make('note')
                    ->label('Cancellation note')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->action(function (Order $record, array $data, OrderService $orderService): void {
                $admin = auth('admin')->user();

                if ($admin === null) {
                    Notification::make()
                        ->title('Authentication required')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $orderService->cancelOrderByAdmin(
                        order: $record,
                        admin: $admin,
                        note: $data['note'] ?? null,
                    );
                } catch (\App\Exceptions\BusinessException $exception) {
                    Notification::make()
                        ->title('Cancellation failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Order cancelled')
                    ->body('Stock has been restored.')
                    ->success()
                    ->send();
            });
    }
}
