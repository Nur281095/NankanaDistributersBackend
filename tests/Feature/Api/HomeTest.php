<?php

use App\Enums\CatalogStatus;
use App\Enums\HomeLinkType;
use App\Enums\HomeSectionType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductCollectionSource;
use App\Models\HomeSection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\HomeSectionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        DemoCatalogSeeder::class,
        HomeSectionsSeeder::class,
    ]);
});

describe('Home API', function (): void {
    it('returns ordered active home sections for the app feed', function (): void {
        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'sections' => [
                        '*' => [
                            'id',
                            'type',
                            'title',
                            'subtitle',
                            'sort_order',
                            'data',
                        ],
                    ],
                ],
            ]);

        $types = collect($response->json('data.sections'))->pluck('type')->all();

        expect($types)->toContain(HomeSectionType::Slider->value)
            ->and($types)->toContain(HomeSectionType::ProductCollection->value)
            ->and($types)->toContain(HomeSectionType::Banner->value);

        $sortOrders = collect($response->json('data.sections'))->pluck('sort_order')->all();
        $sorted = $sortOrders;
        sort($sorted);
        expect($sortOrders)->toBe($sorted);
    });

    it('hides inactive sections from the home feed', function (): void {
        HomeSection::query()
            ->where('title', 'On sale')
            ->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/home');

        $titles = collect($response->json('data.sections'))->pluck('title')->all();

        expect($titles)->not->toContain('On sale')
            ->and($titles)->toContain('New arrivals');
    });

    it('resolves product collection sources including sale and featured', function (): void {
        $response = $this->getJson('/api/v1/home');

        $collections = collect($response->json('data.sections'))
            ->where('type', HomeSectionType::ProductCollection->value)
            ->keyBy('title');

        expect($collections['New arrivals']['data']['source'])->toBe(ProductCollectionSource::NewArrivals->value)
            ->and($collections['On sale']['data']['products'])->not->toBeEmpty()
            ->and($collections['Featured']['data']['products'])->not->toBeEmpty()
            ->and($collections['Staff picks']['data']['source'])->toBe(ProductCollectionSource::Manual->value)
            ->and($collections['Staff picks']['data']['products'])->not->toBeEmpty();

        $saleProduct = $collections['On sale']['data']['products'][0];
        expect($saleProduct)->toHaveKeys(['id', 'name', 'slug', 'regular_price', 'sale_price', 'in_stock', 'brand'])
            ->and($saleProduct)->not->toHaveKey('purchase_price');
    });

    it('ranks top selling products by non-cancelled order quantity', function (): void {
        $nido = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $pepsi = Product::query()->where('sku_code', 'PEPSI-1.5L')->firstOrFail();
        $user = User::factory()->create();

        $delivered = Order::query()->create([
            'order_number' => 'ORD-HOME-1',
            'user_id' => $user->id,
            'is_guest' => false,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'delivery_address' => 'Test address',
            'subtotal' => 500,
            'delivery_charges' => 0,
            'discount_amount' => 0,
            'grand_total' => 500,
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => OrderPaymentStatus::Paid,
            'order_status' => OrderStatus::Delivered,
        ]);

        OrderItem::query()->create([
            'order_id' => $delivered->id,
            'product_id' => $pepsi->id,
            'product_name' => $pepsi->name,
            'sku_code' => $pepsi->sku_code,
            'unit_price' => 100,
            'quantity' => 5,
            'total_price' => 500,
        ]);

        $cancelled = Order::query()->create([
            'order_number' => 'ORD-HOME-2',
            'user_id' => $user->id,
            'is_guest' => false,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'delivery_address' => 'Test address',
            'subtotal' => 2000,
            'delivery_charges' => 0,
            'discount_amount' => 0,
            'grand_total' => 2000,
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => OrderPaymentStatus::CodPending,
            'order_status' => OrderStatus::Cancelled,
        ]);

        OrderItem::query()->create([
            'order_id' => $cancelled->id,
            'product_id' => $nido->id,
            'product_name' => $nido->name,
            'sku_code' => $nido->sku_code,
            'unit_price' => 100,
            'quantity' => 20,
            'total_price' => 2000,
        ]);

        $response = $this->getJson('/api/v1/home');

        $topSelling = collect($response->json('data.sections'))
            ->firstWhere('title', 'Top selling');

        expect($topSelling['data']['products'][0]['sku_code'])->toBe('PEPSI-1.5L');
    });

    it('omits slider sections that have no active slides with images', function (): void {
        $hero = HomeSection::query()->where('title', 'Hero slider')->firstOrFail();
        $hero->slider?->slides()->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/home');

        $titles = collect($response->json('data.sections'))->pluck('title')->all();

        expect($titles)->not->toContain('Hero slider');
    });

    it('resolves slide and banner links and drops invalid targets', function (): void {
        $hero = HomeSection::query()->where('title', 'Hero slider')->firstOrFail();
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();

        $hero->slider?->slides()->update([
            'is_active' => true,
            'link_type' => HomeLinkType::Product,
            'link_value' => (string) $product->id,
        ]);

        $bannerSection = HomeSection::query()->where('title', 'Promo banner')->firstOrFail();
        $bannerSection->banner?->update([
            'link_type' => HomeLinkType::Product,
            'link_value' => '999999',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/home');

        $slider = collect($response->json('data.sections'))->firstWhere('title', 'Hero slider');
        $banner = collect($response->json('data.sections'))->firstWhere('title', 'Promo banner');

        expect($slider['data']['slides'][0]['link'])->toMatchArray([
            'type' => HomeLinkType::Product->value,
            'value' => (string) $product->id,
            'label' => $product->name,
        ])
            ->and($banner['data']['link'])->toBeNull();
    });

    it('excludes inactive catalog products from dynamic collections', function (): void {
        Product::query()->where('sku_code', 'NIDO-400G')->update([
            'status' => CatalogStatus::Inactive,
            'is_featured' => true,
        ]);

        $response = $this->getJson('/api/v1/home');

        $featured = collect($response->json('data.sections'))->firstWhere('title', 'Featured');
        $skus = collect($featured['data']['products'])->pluck('sku_code')->all();

        expect($skus)->not->toContain('NIDO-400G');
    });
});
