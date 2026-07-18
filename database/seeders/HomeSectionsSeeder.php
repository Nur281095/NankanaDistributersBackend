<?php

namespace Database\Seeders;

use App\Enums\HomeLinkType;
use App\Enums\HomeSectionType;
use App\Enums\ProductCollectionSource;
use App\Models\HomeSection;
use App\Models\Product;
use Illuminate\Database\Seeder;

class HomeSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $hero = HomeSection::query()->updateOrCreate(
            [
                'type' => HomeSectionType::Slider,
                'title' => 'Hero slider',
            ],
            [
                'subtitle' => 'Promotional carousel on the app home screen',
                'sort_order' => 10,
                'is_active' => true,
                'product_source' => null,
                'product_limit' => null,
            ],
        );

        $slider = $hero->slider()->firstOrCreate([], [
            'autoplay' => true,
            'interval_ms' => 4000,
        ]);

        if ($slider->slides()->count() === 0) {
            $featuredProduct = Product::query()->active()->orderBy('id')->first();

            $slider->slides()->create([
                'image' => 'marketing/home/slides/placeholder-hero.jpg',
                'title' => 'Welcome to Nankana Distributors',
                'subtitle' => 'Quality brands delivered to your shop',
                'link_type' => $featuredProduct !== null ? HomeLinkType::Product : HomeLinkType::None,
                'link_value' => $featuredProduct?->id,
                'sort_order' => 0,
                'is_active' => true,
            ]);
        }

        HomeSection::query()->updateOrCreate(
            [
                'type' => HomeSectionType::ProductCollection,
                'title' => 'New arrivals',
            ],
            [
                'subtitle' => 'Latest products added to the catalog',
                'sort_order' => 20,
                'is_active' => true,
                'product_source' => ProductCollectionSource::NewArrivals,
                'product_limit' => 10,
            ],
        );

        $promoBanner = HomeSection::query()->updateOrCreate(
            [
                'type' => HomeSectionType::Banner,
                'title' => 'Promo banner',
            ],
            [
                'subtitle' => null,
                'sort_order' => 30,
                'is_active' => true,
                'product_source' => null,
                'product_limit' => null,
            ],
        );

        $promoBanner->banner()->updateOrCreate(
            ['home_section_id' => $promoBanner->id],
            [
                'image' => 'marketing/home/banners/placeholder-promo.jpg',
                'title' => 'Special offers this week',
                'link_type' => HomeLinkType::None,
                'link_value' => null,
                'is_active' => true,
            ],
        );

        HomeSection::query()->updateOrCreate(
            [
                'type' => HomeSectionType::ProductCollection,
                'title' => 'On sale',
            ],
            [
                'subtitle' => 'Products with an active sale price',
                'sort_order' => 40,
                'is_active' => true,
                'product_source' => ProductCollectionSource::OnSale,
                'product_limit' => 10,
            ],
        );

        HomeSection::query()->updateOrCreate(
            [
                'type' => HomeSectionType::ProductCollection,
                'title' => 'Top selling',
            ],
            [
                'subtitle' => 'Best sellers by order volume',
                'sort_order' => 50,
                'is_active' => true,
                'product_source' => ProductCollectionSource::TopSelling,
                'product_limit' => 10,
            ],
        );

        HomeSection::query()->updateOrCreate(
            [
                'type' => HomeSectionType::ProductCollection,
                'title' => 'Featured',
            ],
            [
                'subtitle' => 'Hand-picked featured products',
                'sort_order' => 60,
                'is_active' => true,
                'product_source' => ProductCollectionSource::Featured,
                'product_limit' => 10,
            ],
        );

        $manual = HomeSection::query()->updateOrCreate(
            [
                'type' => HomeSectionType::ProductCollection,
                'title' => 'Staff picks',
            ],
            [
                'subtitle' => 'Manually curated product row',
                'sort_order' => 70,
                'is_active' => true,
                'product_source' => ProductCollectionSource::Manual,
                'product_limit' => 10,
            ],
        );

        $productIds = Product::query()->active()->orderBy('id')->limit(4)->pluck('id');

        if ($productIds->isNotEmpty()) {
            $sync = [];
            foreach ($productIds->values() as $index => $productId) {
                $sync[$productId] = ['sort_order' => $index];
            }
            $manual->products()->sync($sync);
        }
    }
}
