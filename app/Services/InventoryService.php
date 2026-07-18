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

            return InventoryLog::query()->create([
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
        });
    }

    public function decrementForOrder(Product $product, int $quantity, int $orderId): void
    {
        $lockedProduct = Product::query()
            ->lockForUpdate()
            ->whereKey($product->id)
            ->firstOrFail();

        $oldQuantity = $lockedProduct->stock_quantity;
        $newQuantity = $oldQuantity - $quantity;

        if ($newQuantity < 0) {
            throw new BusinessException(
                "Insufficient stock for {$lockedProduct->name}.",
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $lockedProduct->update(['stock_quantity' => $newQuantity]);

        InventoryLog::query()->create([
            'product_id' => $lockedProduct->id,
            'type' => InventoryLogType::OrderPlaced,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'quantity_difference' => -$quantity,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'note' => 'Stock reduced for order placement.',
        ]);
    }

    public function restoreForCancelledOrder(Product $product, int $quantity, int $orderId): void
    {
        $lockedProduct = Product::query()
            ->lockForUpdate()
            ->whereKey($product->id)
            ->firstOrFail();

        $oldQuantity = $lockedProduct->stock_quantity;
        $newQuantity = $oldQuantity + $quantity;

        $lockedProduct->update(['stock_quantity' => $newQuantity]);

        InventoryLog::query()->create([
            'product_id' => $lockedProduct->id,
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
