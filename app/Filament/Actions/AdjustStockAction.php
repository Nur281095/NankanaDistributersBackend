<?php

namespace App\Filament\Actions;

use App\Enums\InventoryLogType;
use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AdjustStockAction
{
    public static function make(): Action
    {
        return Action::make('adjustStock')
            ->label('Adjust stock')
            ->icon('heroicon-o-arrows-up-down')
            ->color('warning')
            ->modalHeading('Adjust product stock')
            ->modalDescription('Changes are recorded in the inventory audit log.')
            ->form([
                Forms\Components\Select::make('direction')
                    ->label('Direction')
                    ->options([
                        'add' => 'Add stock',
                        'remove' => 'Remove stock',
                    ])
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Forms\Components\Select::make('type')
                    ->label('Reason')
                    ->options(collect([
                        InventoryLogType::ManualAdjustment,
                        InventoryLogType::Added,
                        InventoryLogType::Removed,
                        InventoryLogType::Damaged,
                        InventoryLogType::Returned,
                    ])->mapWithKeys(
                        fn (InventoryLogType $type): array => [$type->value => Str::headline($type->value)]
                    )->all())
                    ->default(InventoryLogType::ManualAdjustment->value)
                    ->required()
                    ->native(false),
                Forms\Components\Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->action(function (Product $record, array $data, InventoryService $inventoryService): void {
                $admin = auth('admin')->user();

                if ($admin === null) {
                    Notification::make()
                        ->title('Authentication required')
                        ->danger()
                        ->send();

                    return;
                }

                Gate::forUser($admin)->authorize('update', $record);

                $quantity = (int) $data['quantity'];
                $quantityChange = $data['direction'] === 'remove' ? -$quantity : $quantity;

                try {
                    $inventoryService->adjustStock(
                        product: $record,
                        quantityChange: $quantityChange,
                        admin: $admin,
                        type: InventoryLogType::from($data['type']),
                        note: $data['note'] ?? null,
                    );
                } catch (BusinessException $exception) {
                    Notification::make()
                        ->title('Stock adjustment failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Stock updated')
                    ->body("New stock level: {$record->fresh()->stock_quantity}")
                    ->success()
                    ->send();
            });
    }
}
