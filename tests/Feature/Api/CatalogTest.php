<?php

use App\Enums\CatalogStatus;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductImage;
use Database\Seeders\DemoCatalogSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DemoCatalogSeeder::class);
});

/**
 * @param  array<string, mixed>  $payload
 */
function assertDoesNotExposePurchasePrice(array $payload): void
{
    foreach ($payload as $key => $value) {
        expect($key)->not->toBe('purchase_price');

        if (is_array($value)) {
            assertDoesNotExposePurchasePrice($value);
        }
    }
}

describe('Catalog API', function (): void {
    it('lists active companies with pagination', function (): void {
        Company::query()->create([
            'name' => 'Inactive Co',
            'slug' => 'inactive-co',
            'status' => CatalogStatus::Inactive,
            'sort_order' => 99,
        ]);

        $response = $this->getJson('/api/v1/companies?per_page=1&page=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'status', 'sort_order'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        assertDoesNotExposePurchasePrice($response->json());
    });

    it('shows company detail with active brand count', function (): void {
        $company = Company::query()->where('slug', 'nestle')->firstOrFail();

        Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Inactive Brand',
            'slug' => 'inactive-brand',
            'status' => CatalogStatus::Inactive,
            'sort_order' => 99,
        ]);

        $response = $this->getJson("/api/v1/companies/{$company->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'nestle')
            ->assertJsonPath('data.brands_count', 1);
    });

    it('lists active brands for a company', function (): void {
        $company = Company::query()->where('slug', 'nestle')->firstOrFail();

        $response = $this->getJson("/api/v1/companies/{$company->id}/brands");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'nido');
    });

    it('shows brand detail with company and product count', function (): void {
        $brand = Brand::query()->where('slug', 'nido')->firstOrFail();

        Product::query()->create([
            'company_id' => $brand->company_id,
            'brand_id' => $brand->id,
            'name' => 'Inactive Product',
            'slug' => 'inactive-product',
            'sku_code' => 'INACTIVE-001',
            'regular_price' => 100,
            'purchase_price' => 50,
            'stock_quantity' => 0,
            'status' => CatalogStatus::Inactive,
        ]);

        $response = $this->getJson("/api/v1/brands/{$brand->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'nido')
            ->assertJsonPath('data.products_count', 1)
            ->assertJsonPath('data.company.slug', 'nestle');
    });

    it('lists active products for a brand', function (): void {
        $brand = Brand::query()->where('slug', 'nido')->firstOrFail();

        $response = $this->getJson("/api/v1/brands/{$brand->id}/products");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku_code', 'NIDO-400G')
            ->assertJsonPath('data.0.in_stock', true);

        assertDoesNotExposePurchasePrice($response->json());
    });

    it('shows product detail with description and ordered images', function (): void {
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();

        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.com/nido-2.jpg',
            'sort_order' => 2,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.com/nido-1.jpg',
            'sort_order' => 1,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku_code', 'NIDO-400G')
            ->assertJsonPath('data.description', 'Nido fortified milk powder 400g pack.')
            ->assertJsonPath('data.low_stock_threshold', 10)
            ->assertJsonPath('data.brand.slug', 'nido')
            ->assertJsonPath('data.images.0.image_url', 'https://cdn.example.com/nido-1.jpg')
            ->assertJsonPath('data.images.1.image_url', 'https://cdn.example.com/nido-2.jpg');

        assertDoesNotExposePurchasePrice($response->json());
    });

    it('searches products by name and sku', function (): void {
        $byName = $this->getJson('/api/v1/products/search?q=nido');
        $byName->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.sku_code', 'NIDO-400G');

        $bySku = $this->getJson('/api/v1/products/search?q=PEPSI-1.5L');
        $bySku->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.slug', 'pepsi-1-5l');

        assertDoesNotExposePurchasePrice($byName->json());
        assertDoesNotExposePurchasePrice($bySku->json());
    });

    it('validates product search query length', function (): void {
        $response = $this->getJson('/api/v1/products/search?q=a');

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['q']]);
    });

    it('lists suggested products only', function (): void {
        $response = $this->getJson('/api/v1/products/suggested');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_suggested', true)
            ->assertJsonPath('data.0.sku_code', 'NIDO-400G');
    });

    it('returns not found for inactive catalog records', function (): void {
        $inactiveCompany = Company::query()->create([
            'name' => 'Hidden Co',
            'slug' => 'hidden-co',
            'status' => CatalogStatus::Inactive,
            'sort_order' => 50,
        ]);

        $inactiveBrand = Brand::query()->create([
            'company_id' => Company::query()->where('slug', 'nestle')->value('id'),
            'name' => 'Hidden Brand',
            'slug' => 'hidden-brand',
            'status' => CatalogStatus::Inactive,
            'sort_order' => 50,
        ]);

        $inactiveProduct = Product::query()->create([
            'company_id' => Company::query()->where('slug', 'nestle')->value('id'),
            'brand_id' => Brand::query()->where('slug', 'nido')->value('id'),
            'name' => 'Hidden Product',
            'slug' => 'hidden-product',
            'sku_code' => 'HIDDEN-001',
            'regular_price' => 100,
            'purchase_price' => 50,
            'stock_quantity' => 10,
            'status' => CatalogStatus::Inactive,
        ]);

        $this->getJson("/api/v1/companies/{$inactiveCompany->id}")->assertNotFound();
        $this->getJson("/api/v1/brands/{$inactiveBrand->id}")->assertNotFound();
        $this->getJson("/api/v1/products/{$inactiveProduct->id}")->assertNotFound();
    });

    it('hides products from inactive parent company or brand', function (): void {
        $company = Company::query()->create([
            'name' => 'Soon Inactive Co',
            'slug' => 'soon-inactive-co',
            'status' => CatalogStatus::Active,
            'sort_order' => 60,
        ]);

        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Soon Inactive Brand',
            'slug' => 'soon-inactive-brand',
            'status' => CatalogStatus::Active,
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'company_id' => $company->id,
            'brand_id' => $brand->id,
            'name' => 'Soon Hidden Product',
            'slug' => 'soon-hidden-product',
            'sku_code' => 'SOON-HIDDEN',
            'regular_price' => 100,
            'purchase_price' => 50,
            'stock_quantity' => 10,
            'status' => CatalogStatus::Active,
        ]);

        $company->update(['status' => CatalogStatus::Inactive]);

        $this->getJson("/api/v1/products/{$product->id}")->assertNotFound();
        $this->getJson("/api/v1/brands/{$brand->id}/products")->assertNotFound();
    });
});
