<?php

namespace App\Services;

use App\Enums\AdminStatus;
use App\Enums\CatalogStatus;
use App\Enums\NotificationType;
use App\Models\Admin;
use App\Models\Product;
use App\Support\EmailPlaceholderBuilder;
use Illuminate\Support\Facades\Cache;

class LowStockAlertService
{
    private const CACHE_PREFIX = 'low_stock_alert:';

    private const DEBOUNCE_SECONDS = 86400;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EmailService $emailService,
        private readonly SettingsService $settingsService,
    ) {}

    public function checkAfterStockChange(Product $product, int $oldQuantity, int $newQuantity): void
    {
        $this->checkIfEnteredLowStock(
            product: $product,
            previousQuantity: $oldQuantity,
            previousThreshold: $product->low_stock_threshold,
            currentQuantity: $newQuantity,
        );
    }

    public function checkIfEnteredLowStock(
        Product $product,
        int $previousQuantity,
        int $previousThreshold,
        ?int $currentQuantity = null,
    ): void {
        $quantity = $currentQuantity ?? $product->stock_quantity;
        $threshold = $product->low_stock_threshold;

        $wasLow = $previousQuantity <= $previousThreshold;
        $isLow = $quantity <= $threshold;

        if (! $isLow || $wasLow) {
            return;
        }

        $this->alertIfNeeded($product, $quantity);
    }

    public function sweep(): int
    {
        $alerted = 0;

        Product::query()
            ->where('status', CatalogStatus::Active)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('id')
            ->each(function (Product $product) use (&$alerted): void {
                if (! $this->shouldSendAlert($product->id)) {
                    return;
                }

                $this->notifyAdmins($product, $product->stock_quantity);
                $this->markAlertSent($product->id);
                $alerted++;
            });

        return $alerted;
    }

    private function alertIfNeeded(Product $product, int $stockQuantity): void
    {
        if (! $this->shouldSendAlert($product->id)) {
            return;
        }

        $this->notifyAdmins($product, $stockQuantity);
        $this->markAlertSent($product->id);
    }

    private function shouldSendAlert(int $productId): bool
    {
        return ! Cache::has(self::CACHE_PREFIX.$productId);
    }

    private function markAlertSent(int $productId): void
    {
        Cache::put(self::CACHE_PREFIX.$productId, true, self::DEBOUNCE_SECONDS);
    }

    private function notifyAdmins(Product $product, int $stockQuantity): void
    {
        $title = 'Low stock alert';
        $message = "{$product->name} ({$product->sku_code}) is low: {$stockQuantity} remaining.";

        $data = [
            'product_id' => $product->id,
            'sku_code' => $product->sku_code,
            'stock_quantity' => $stockQuantity,
        ];

        Admin::query()
            ->where('status', AdminStatus::Active)
            ->each(function (Admin $admin) use ($title, $message, $data, $product): void {
                $this->notificationService->queueForAdmin(
                    admin: $admin,
                    title: $title,
                    message: $message,
                    type: NotificationType::LowStock,
                    data: $data,
                    referenceType: 'product',
                    referenceId: $product->id,
                );
            });

        $supportEmail = (string) $this->settingsService->get('support_email');

        if ($supportEmail !== '' && filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $this->emailService->queue(
                templateSlug: 'low_stock_alert',
                recipient: $supportEmail,
                placeholders: EmailPlaceholderBuilder::forProduct($product, $stockQuantity),
                referenceType: 'product',
                referenceId: $product->id,
            );
        }
    }
}
