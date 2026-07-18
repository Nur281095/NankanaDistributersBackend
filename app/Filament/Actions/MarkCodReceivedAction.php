<?php

namespace App\Filament\Actions;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

class MarkCodReceivedAction
{
    public static function make(): Action
    {
        return Action::make('markCodReceived')
            ->label('Mark COD received')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Confirm COD collection')
            ->modalDescription('This marks the cash-on-delivery payment as collected.')
            ->visible(fn (Order $record, OrderService $orderService): bool => $orderService->canMarkCodReceived($record))
            ->form([
                Forms\Components\Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->maxLength(500)
                    ->placeholder('Optional note about cash collection.'),
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

                Gate::forUser($admin)->authorize('update', $record);

                try {
                    $orderService->markCodReceived(
                        order: $record,
                        admin: $admin,
                        note: $data['note'] ?? null,
                    );
                } catch (BusinessException $exception) {
                    Notification::make()
                        ->title('COD confirmation failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('COD marked as received')
                    ->success()
                    ->send();
            });
    }
}
