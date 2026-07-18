<?php

namespace App\Filament\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UpdateOrderStatusAction
{
    public static function make(): Action
    {
        return Action::make('updateOrderStatus')
            ->label('Update status')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->visible(fn (Order $record, OrderService $orderService): bool => $orderService->allowedNextStatuses($record) !== [])
            ->modalHeading('Update order status')
            ->form(function (Order $record, OrderService $orderService): array {
                $forwardStatuses = collect($orderService->allowedNextStatuses($record))
                    ->reject(fn (OrderStatus $status): bool => $status === OrderStatus::Cancelled)
                    ->mapWithKeys(
                        fn (OrderStatus $status): array => [$status->value => Str::headline($status->value)]
                    );

                return [
                    Forms\Components\Select::make('order_status')
                        ->label('New status')
                        ->options($forwardStatuses->all())
                        ->required()
                        ->native(false),
                    Forms\Components\Textarea::make('note')
                        ->label('Note')
                        ->rows(3)
                        ->maxLength(500),
                ];
            })
            ->action(function (Order $record, array $data, OrderService $orderService): void {
                $admin = auth('admin')->user();

                if ($admin === null) {
                    Notification::make()
                        ->title('Authentication required')
                        ->danger()
                        ->send();

                    return;
                }

                Gate::forUser($admin)->authorize('update', $record);

                try {
                    $orderService->advanceOrderStatus(
                        order: $record,
                        newStatus: OrderStatus::from($data['order_status']),
                        admin: $admin,
                        note: $data['note'] ?? null,
                    );
                } catch (BusinessException $exception) {
                    Notification::make()
                        ->title('Status update failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Order status updated')
                    ->body('Status is now '.Str::headline($record->fresh()->order_status->value).'.')
                    ->success()
                    ->send();
            });
    }
}
