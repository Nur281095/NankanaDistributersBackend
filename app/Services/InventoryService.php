<?php

namespace App\Services;

use App\Enums\InventoryLogType;
use App\Exceptions\BusinessException;
use App\Models\Admin;
use App\Models\InventoryLog;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InventoryService
{
    public function __construct(
        private readonly LowStockAlertService $lowStockAlertService,
    ) {}

    public function adjustStock(
        Product $product,
        int $quantityChange,
        Admin $admin,
        InventoryLogType $type = InventoryLogType::ManualAdjustment,
        ?string $note = null,
    ): InventoryLog {
        if ($quantityChange === 0) {
            throw new BusinessException(
                'Stock adjustment quantity cannot be zero.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return DB::transaction(function () use ($product, $quantityChange, $admin, $type, $note): InventoryLog {
            $lockedProduct = Product::query()
                ->lockForUpdate()
                ->whereKey($product->id)
                ->firstOrFail();

            $oldQuantity = $lockedProduct->stock_quantity;
            $newQuantity = $oldQuantity + $quantityChange;

            if ($newQuantity < 0) {
                throw new BusinessException(
                    "Insufficient stock for {$lockedProduct->name}. Current stock: {$oldQuantity}.",
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $lockedProduct->update(['stock_quantity' => $newQuantity]);

            $log = InventoryLog::query()->create([
                'product_id' => $lockedProduct->id,
                'admin_id' => $admin->id,
                'type' => $type,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'quantity_difference' => $quantityChange,
                'reference_type' => 'manual_adjustment',
                'reference_id' => null,
                'note' => $note,
            ]);

            $this->lowStockAlertService->checkAfterStockChange(
                product: $lockedProduct->fresh(),
                oldQuantity: $oldQuantity,
                newQuantity: $newQuantity,
            );

            return $log;
        });
    }

    public function decrementForOrder(Product $product, int $quantity, int $orderId): void
    {
        $oldQuantity = $product->stock_quantity;
        $newQuantity = $oldQuantity - $quantity;

        if ($newQuantity < 0) {
            throw new BusinessException(
                "Insufficient stock for {$product->name}.",
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $product->update(['stock_quantity' => $newQuantity]);

        InventoryLog::query()->create([
            'product_id' => $product->id,
            'type' => InventoryLogType::OrderPlaced,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'quantity_difference' => -$quantity,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'note' => 'Stock reduced for order placement.',
        ]);

        $this->lowStockAlertService->checkAfterStockChange(
            product: $product->fresh(),
            oldQuantity: $oldQuantity,
            newQuantity: $newQuantity,
        );
    }

    public function restoreForCancelledOrder(Product $product, int $quantity, int $orderId): void
    {
        $oldQuantity = $product->stock_quantity;
        $newQuantity = $oldQuantity + $quantity;

        $product->update(['stock_quantity' => $newQuantity]);

        InventoryLog::query()->create([
            'product_id' => $product->id,
            'type' => InventoryLogType::OrderCancelled,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'quantity_difference' => $quantity,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'note' => 'Stock restored after order cancellation.',
        ]);
    }
}
