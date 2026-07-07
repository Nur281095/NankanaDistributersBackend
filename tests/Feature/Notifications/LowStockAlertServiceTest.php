<?php

use App\Enums\EmailLogStatus;
use App\Enums\InventoryLogType;
use App\Enums\NotificationType;
use App\Jobs\SendAppNotificationJob;
use App\Jobs\SendTemplatedEmailJob;
use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\EmailLog;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\LowStockAlertService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();

    $this->seed([
        AdminSeeder::class,
        SettingsSeeder::class,
        EmailTemplateSeeder::class,
        DemoCatalogSeeder::class,
    ]);

    $this->admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
    $this->product = Product::query()->firstOrFail();
});

describe('LowStockAlertService', function (): void {
    it('notifies admins and queues a low stock email when stock crosses the threshold', function (): void {
        Queue::fake();

        $this->product->update([
            'stock_quantity' => 12,
            'low_stock_threshold' => 10,
        ]);

        app(LowStockAlertService::class)->checkAfterStockChange(
            product: $this->product->fresh(),
            oldQuantity: 12,
            newQuantity: 9,
        );

        Queue::assertPushed(SendAppNotificationJob::class, function (SendAppNotificationJob $job): bool {
            return $job->adminId === $this->admin->id
                && $job->type === NotificationType::LowStock;
        });

        Queue::assertPushed(SendTemplatedEmailJob::class);

        expect(AppNotification::query()->where('type', NotificationType::LowStock)->count())->toBe(0);
    });

    it('does not alert when stock was already below the threshold', function (): void {
        Queue::fake();

        $this->product->update([
            'stock_quantity' => 8,
            'low_stock_threshold' => 10,
        ]);

        app(LowStockAlertService::class)->checkAfterStockChange(
            product: $this->product->fresh(),
            oldQuantity: 8,
            newQuantity: 6,
        );

        Queue::assertNothingPushed();
    });

    it('debounces repeated low stock alerts for the same product', function (): void {
        Queue::fake();

        $this->product->update([
            'stock_quantity' => 15,
            'low_stock_threshold' => 10,
        ]);

        $service = app(LowStockAlertService::class);

        $service->checkAfterStockChange($this->product->fresh(), 15, 10);
        $service->checkAfterStockChange($this->product->fresh(), 15, 9);

        Queue::assertPushed(SendAppNotificationJob::class, 1);
        Queue::assertPushed(SendTemplatedEmailJob::class, 1);
    });
});

describe('InventoryService low stock integration', function (): void {
    it('triggers a low stock alert after a manual stock adjustment crosses the threshold', function (): void {
        Queue::fake();

        $this->product->update([
            'stock_quantity' => 12,
            'low_stock_threshold' => 10,
        ]);

        app(InventoryService::class)->adjustStock(
            product: $this->product,
            quantityChange: -3,
            admin: $this->admin,
            type: InventoryLogType::ManualAdjustment,
            note: 'Damaged units removed',
        );

        Queue::assertPushed(SendAppNotificationJob::class);
        Queue::assertPushed(SendTemplatedEmailJob::class);

        $this->assertDatabaseHas('email_logs', [
            'status' => EmailLogStatus::Queued->value,
            'reference_type' => 'product',
            'reference_id' => $this->product->id,
        ]);
    });
});
