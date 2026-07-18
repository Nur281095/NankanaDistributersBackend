<?php

namespace App\Models;

use App\Enums\CatalogStatus;
use App\Enums\OfferTargetType;
use App\Models\Concerns\CleansOfferTargets;
use App\Models\Concerns\CleansPublicMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'logo', 'description', 'status', 'sort_order'])]
class Company extends Model
{
    use CleansOfferTargets;
    use CleansPublicMedia;
    use HasFactory;
    use SoftDeletes;

    /**
     * @return list<string>
     */
    protected function publicMediaAttributes(): array
    {
        return ['logo'];
    }

    protected function offerTargetType(): OfferTargetType
    {
        return OfferTargetType::Company;
    }

    /**
     * @return HasMany<Brand, $this>
     */
    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CatalogStatus::Active);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CatalogStatus::class,
            'sort_order' => 'integer',
        ];
    }
}
