<?php

namespace App\Services;

use App\Enums\CatalogStatus;
use App\Enums\HomeLinkType;
use App\Enums\HomeSectionType;
use App\Enums\OrderStatus;
use App\Enums\ProductCollectionSource;
use App\Models\Brand;
use App\Models\Company;
use App\Models\HomeSection;
use App\Models\HomeSliderSlide;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HomeService
{
    public const DEFAULT_PRODUCT_LIMIT = 10;

    public const MAX_PRODUCT_LIMIT = 50;

    /**
     * @return list<array<string, mixed>>
     */
    public function buildHomeFeed(): array
    {
        $sections = HomeSection::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with([
                'slider.slides' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                'banner',
                'products' => fn ($query) => $query
                    ->active()
                    ->whereHas('brand', fn ($brand) => $brand->active())
                    ->whereHas('company', fn ($company) => $company->active())
                    ->with(['brand' => fn ($brand) => $brand->active()]),
            ])
            ->get();

        return $sections
            ->map(fn (HomeSection $section): ?array => $this->serializeSection($section))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeSection(HomeSection $section): ?array
    {
        return match ($section->type) {
            HomeSectionType::ProductCollection => $this->serializeProductCollection($section),
            HomeSectionType::Slider => $this->serializeSlider($section),
            HomeSectionType::Banner => $this->serializeBanner($section),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeProductCollection(HomeSection $section): ?array
    {
        if ($section->product_source === null) {
            return null;
        }

        $products = $this->resolveProducts($section);

        return [
            'id' => $section->id,
            'type' => $section->type->value,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'sort_order' => $section->sort_order,
            'data' => [
                'source' => $section->product_source->value,
                'limit' => $this->productLimit($section),
                'products' => $products->map(fn (Product $product): array => $this->productCard($product))->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeSlider(HomeSection $section): ?array
    {
        $slider = $section->slider;

        if ($slider === null) {
            return null;
        }

        $slides = $slider->slides
            ->filter(fn (HomeSliderSlide $slide): bool => $slide->is_active && filled($slide->image))
            ->values();

        if ($slides->isEmpty()) {
            return null;
        }

        return [
            'id' => $section->id,
            'type' => $section->type->value,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'sort_order' => $section->sort_order,
            'data' => [
                'autoplay' => $slider->autoplay,
                'interval_ms' => $slider->interval_ms,
                'slides' => $slides->map(fn (HomeSliderSlide $slide): array => [
                    'id' => $slide->id,
                    'image' => $slide->image,
                    'title' => $slide->title,
                    'subtitle' => $slide->subtitle,
                    'link' => $this->resolveLink($slide->link_type, $slide->link_value),
                    'sort_order' => $slide->sort_order,
                ])->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeBanner(HomeSection $section): ?array
    {
        $banner = $section->banner;

        if ($banner === null || ! $banner->is_active || blank($banner->image)) {
            return null;
        }

        return [
            'id' => $section->id,
            'type' => $section->type->value,
            'title' => $section->title ?? $banner->title,
            'subtitle' => $section->subtitle,
            'sort_order' => $section->sort_order,
            'data' => [
                'id' => $banner->id,
                'image' => $banner->image,
                'title' => $banner->title,
                'link' => $this->resolveLink($banner->link_type, $banner->link_value),
            ],
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    private function resolveProducts(HomeSection $section): Collection
    {
        $limit = $this->productLimit($section);

        return match ($section->product_source) {
            ProductCollectionSource::Manual => $section->products
                ->take($limit)
                ->values(),
            ProductCollectionSource::NewArrivals => $this->baseProductQuery()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
            ProductCollectionSource::OnSale => $this->baseProductQuery()
                ->whereNotNull('sale_price')
                ->whereColumn('sale_price', '<', 'regular_price')
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get(),
            ProductCollectionSource::Featured => $this->baseProductQuery()
                ->where('is_featured', true)
                ->orderBy('name')
                ->limit($limit)
                ->get(),
            ProductCollectionSource::TopSelling => $this->baseProductQuery()
                ->withSum([
                    'orderItems as units_sold' => function ($query): void {
                        $query->whereHas('order', function ($orderQuery): void {
                            $orderQuery->whereNotIn('order_status', [
                                OrderStatus::Cancelled->value,
                            ]);
                        });
                    },
                ], 'quantity')
                ->orderByRaw('COALESCE(units_sold, 0) DESC')
                ->orderBy('name')
                ->limit($limit)
                ->get(),
            null => collect(),
        };
    }

    /**
     * @return Builder<Product>
     */
    private function baseProductQuery()
    {
        return Product::query()
            ->active()
            ->whereHas('brand', fn ($query) => $query->active())
            ->whereHas('company', fn ($query) => $query->active())
            ->with(['brand' => fn ($query) => $query->active()]);
    }

    private function productLimit(HomeSection $section): int
    {
        $limit = $section->product_limit ?? self::DEFAULT_PRODUCT_LIMIT;

        return max(1, min(self::MAX_PRODUCT_LIMIT, $limit));
    }

    /**
     * @return array<string, mixed>
     */
    private function productCard(Product $product): array
    {
        return [
            'id' => $product->id,
            'company_id' => $product->company_id,
            'brand_id' => $product->brand_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku_code' => $product->sku_code,
            'image' => $product->image,
            'regular_price' => $product->regular_price,
            'sale_price' => $product->sale_price,
            'stock_quantity' => $product->stock_quantity,
            'in_stock' => $product->stock_quantity > 0,
            'unit' => $product->unit,
            'is_featured' => $product->is_featured,
            'is_suggested' => $product->is_suggested,
            'brand' => $product->relationLoaded('brand') && $product->brand !== null
                ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => $product->brand->slug,
                ]
                : null,
        ];
    }

    /**
     * @return array{type: string, value: string|null, label: string|null}|null
     */
    private function resolveLink(HomeLinkType $linkType, ?string $linkValue): ?array
    {
        if ($linkType === HomeLinkType::None) {
            return null;
        }

        $value = trim((string) $linkValue);

        if ($value === '') {
            return null;
        }

        return match ($linkType) {
            HomeLinkType::Url => [
                'type' => $linkType->value,
                'value' => $value,
                'label' => null,
            ],
            HomeLinkType::Product => $this->linkPayload(
                $linkType,
                $value,
                Product::query()->active()->whereKey($value)->value('name'),
            ),
            HomeLinkType::Brand => $this->linkPayload(
                $linkType,
                $value,
                Brand::query()->active()->whereKey($value)->value('name'),
            ),
            HomeLinkType::Company => $this->linkPayload(
                $linkType,
                $value,
                Company::query()->active()->whereKey($value)->value('name'),
            ),
            HomeLinkType::Offer => $this->linkPayload(
                $linkType,
                $value,
                Offer::query()
                    ->where('status', CatalogStatus::Active)
                    ->whereKey($value)
                    ->value('title'),
            ),
            HomeLinkType::None => null,
        };
    }

    /**
     * @return array{type: string, value: string, label: string|null}|null
     */
    private function linkPayload(HomeLinkType $type, string $value, mixed $label): ?array
    {
        if ($label === null) {
            return null;
        }

        return [
            'type' => $type->value,
            'value' => $value,
            'label' => (string) $label,
        ];
    }
}
