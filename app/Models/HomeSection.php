<?php

namespace App\Models;

use App\Enums\HomeSectionType;
use App\Enums\ProductCollectionSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'type',
    'title',
    'subtitle',
    'sort_order',
    'is_active',
    'product_source',
    'product_limit',
    'settings',
])]
class HomeSection extends Model
{
    use SoftDeletes;

    /**
     * @return HasOne<HomeSlider, $this>
     */
    public function slider(): HasOne
    {
        return $this->hasOne(HomeSlider::class);
    }

    /**
     * @return HasOne<HomeBanner, $this>
     */
    public function banner(): HasOne
    {
        return $this->hasOne(HomeBanner::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'home_section_products')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Convenience relation for Filament slide management.
     *
     * @return HasManyThrough<HomeSliderSlide, HomeSlider, $this>
     */
    public function slides(): HasManyThrough
    {
        return $this->hasManyThrough(
            HomeSliderSlide::class,
            HomeSlider::class,
            'home_section_id',
            'home_slider_id',
            'id',
            'id',
        )->orderBy('home_slider_slides.sort_order');
    }

    /**
     * @param  Builder<HomeSection>  $query
     * @return Builder<HomeSection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HomeSectionType::class,
            'product_source' => ProductCollectionSource::class,
            'sort_order' => 'integer',
            'product_limit' => 'integer',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
