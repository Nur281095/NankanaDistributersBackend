<?php

namespace Database\Seeders;

use App\Enums\CatalogStatus;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $nestle = Company::query()->updateOrCreate(
            ['slug' => 'nestle'],
            [
                'name' => 'Nestle',
                'description' => 'Leading nutrition, health and wellness company.',
                'status' => CatalogStatus::Active,
                'sort_order' => 1,
            ],
        );

        $nido = Brand::query()->updateOrCreate(
            ['slug' => 'nido'],
            [
                'company_id' => $nestle->id,
                'name' => 'Nido',
                'description' => 'Fortified milk powder brand.',
                'status' => CatalogStatus::Active,
                'sort_order' => 1,
            ],
        );

        Product::query()->updateOrCreate(
            ['sku_code' => 'NIDO-400G'],
            [
                'company_id' => $nestle->id,
                'brand_id' => $nido->id,
                'name' => 'Nido 400g',
                'slug' => 'nido-400g',
                'description' => 'Nido fortified milk powder 400g pack.',
                'regular_price' => 850.00,
                'sale_price' => 799.00,
                'purchase_price' => 650.00,
                'stock_quantity' => 120,
                'low_stock_threshold' => 10,
                'unit' => '400g',
                'is_taxable' => false,
                'status' => CatalogStatus::Active,
                'is_featured' => true,
                'is_suggested' => true,
            ],
        );

        $pepsiCo = Company::query()->updateOrCreate(
            ['slug' => 'pepsico'],
            [
                'name' => 'PepsiCo',
                'description' => 'Global food and beverage company.',
                'status' => CatalogStatus::Active,
                'sort_order' => 2,
            ],
        );

        $pepsi = Brand::query()->updateOrCreate(
            ['slug' => 'pepsi'],
            [
                'company_id' => $pepsiCo->id,
                'name' => 'Pepsi',
                'description' => 'Popular soft drink brand.',
                'status' => CatalogStatus::Active,
                'sort_order' => 1,
            ],
        );

        Product::query()->updateOrCreate(
            ['sku_code' => 'PEPSI-1.5L'],
            [
                'company_id' => $pepsiCo->id,
                'brand_id' => $pepsi->id,
                'name' => 'Pepsi 1.5L',
                'slug' => 'pepsi-1-5l',
                'description' => 'Pepsi carbonated soft drink 1.5 litre bottle.',
                'regular_price' => 220.00,
                'sale_price' => null,
                'purchase_price' => 170.00,
                'stock_quantity' => 200,
                'low_stock_threshold' => 15,
                'unit' => '1.5L',
                'is_taxable' => false,
                'status' => CatalogStatus::Active,
                'is_featured' => true,
                'is_suggested' => false,
            ],
        );
    }
}
