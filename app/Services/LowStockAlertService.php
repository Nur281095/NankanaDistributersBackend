<?php

namespace App\Services;

use App\Enums\AdminStatus;
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
        if ($newQuantity > $product->low_stock_threshold) {
            return;
        }

        if ($oldQuantity <= $product->low_stock_threshold) {
            return;
        }

        if (! $this->shouldSendAlert($product->id)) {
            return;
        }

        $this->notifyAdmins($product, $newQuantity);
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
