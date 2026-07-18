<?php

use App\Enums\CatalogStatus;
use App\Enums\DiscountType;
use App\Enums\NotificationType;
use App\Jobs\SendAppNotificationJob;
use App\Models\AppNotification;
use App\Models\Offer;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();

    $this->seed([
        AdminSeeder::class,
        SettingsSeeder::class,
        EmailTemplateSeeder::class,
        DemoCatalogSeeder::class,
    ]);
});

it('expires offers whose end date has passed', function (): void {
    $offer = Offer::query()->create([
        'title' => 'Old Offer',
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 10,
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
        'status' => CatalogStatus::Active,
    ]);

    Artisan::call('offers:expire');

    expect($offer->fresh()->status)->toBe(CatalogStatus::Inactive);
});

it('sweeps currently low-stock products for admin alerts', function (): void {
    Queue::fake();

    $product = Product::query()->firstOrFail();
    Product::withoutEvents(fn () => $product->update([
        'stock_quantity' => 3,
        'low_stock_threshold' => 10,
    ]));

    Artisan::call('inventory:sweep-low-stock');

    Queue::assertPushed(SendAppNotificationJob::class);
    expect(
        AppNotification::query()
            ->where('type', NotificationType::LowStock)
            ->exists()
    )->toBeTrue();
});
