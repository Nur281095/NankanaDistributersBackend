<?php

use App\Enums\CatalogStatus;
use App\Enums\DiscountType;
use App\Enums\OfferTargetType;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferTarget;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\PublicStorageCleanup;
use Illuminate\Support\Facades\Storage;

it('deletes replaced public disk files', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('catalog/companies/old.png', 'old');
    Storage::disk('public')->put('catalog/companies/new.png', 'new');

    PublicStorageCleanup::deleteReplaced(
        'catalog/companies/old.png',
        'catalog/companies/new.png',
    );

    Storage::disk('public')->assertMissing('catalog/companies/old.png');
    Storage::disk('public')->assertExists('catalog/companies/new.png');
});

it('removes company logo from disk on force delete', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('catalog/companies/logo.png', 'logo');

    $company = Company::query()->create([
        'name' => 'Storage Co',
        'slug' => 'storage-co',
        'logo' => 'catalog/companies/logo.png',
        'status' => CatalogStatus::Active,
        'sort_order' => 1,
    ]);

    $company->forceDelete();

    Storage::disk('public')->assertMissing('catalog/companies/logo.png');
});

it('removes offer targets when a product is deleted', function (): void {
    $company = Company::query()->create([
        'name' => 'Target Co',
        'slug' => 'target-co',
        'status' => CatalogStatus::Active,
        'sort_order' => 1,
    ]);
    $brand = Brand::query()->create([
        'company_id' => $company->id,
        'name' => 'Target Brand',
        'slug' => 'target-brand',
        'status' => CatalogStatus::Active,
        'sort_order' => 1,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->id,
        'brand_id' => $brand->id,
        'name' => 'Target Product',
        'slug' => 'target-product',
        'sku_code' => 'TARGET-SKU-1',
        'regular_price' => 100,
        'stock_quantity' => 5,
        'low_stock_threshold' => 1,
        'status' => CatalogStatus::Active,
    ]);

    $offer = Offer::query()->create([
        'title' => 'Test offer',
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 10,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'status' => CatalogStatus::Active,
    ]);

    OfferTarget::query()->create([
        'offer_id' => $offer->id,
        'target_type' => OfferTargetType::Product,
        'target_id' => $product->id,
    ]);

    $product->delete();

    expect(
        OfferTarget::query()
            ->where('target_type', OfferTargetType::Product)
            ->where('target_id', $product->id)
            ->exists()
    )->toBeFalse();
});

it('removes gallery images from disk when a product is force deleted', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('catalog/products/main.png', 'main');
    Storage::disk('public')->put('catalog/products/gallery/one.png', 'one');

    $company = Company::query()->create([
        'name' => 'Gallery Co',
        'slug' => 'gallery-co',
        'status' => CatalogStatus::Active,
        'sort_order' => 1,
    ]);
    $brand = Brand::query()->create([
        'company_id' => $company->id,
        'name' => 'Gallery Brand',
        'slug' => 'gallery-brand',
        'status' => CatalogStatus::Active,
        'sort_order' => 1,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->id,
        'brand_id' => $brand->id,
        'name' => 'Gallery Product',
        'slug' => 'gallery-product',
        'sku_code' => 'GALLERY-SKU-1',
        'image' => 'catalog/products/main.png',
        'regular_price' => 100,
        'stock_quantity' => 5,
        'low_stock_threshold' => 1,
        'status' => CatalogStatus::Active,
    ]);

    ProductImage::query()->create([
        'product_id' => $product->id,
        'image_url' => 'catalog/products/gallery/one.png',
        'sort_order' => 1,
    ]);

    $product->forceDelete();

    Storage::disk('public')->assertMissing('catalog/products/main.png');
    Storage::disk('public')->assertMissing('catalog/products/gallery/one.png');
});
