<?php

namespace App\Models;

use App\Enums\CatalogStatus;
use App\Enums\OfferTargetType;
use App\Models\Concerns\CleansOfferTargets;
use App\Models\Concerns\CleansPublicMedia;
use App\Services\LowStockAlertService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'brand_id',
    'name',
    'slug',
    'sku_code',
    'description',
    'image',
    'regular_price',
    'sale_price',
    'purchase_price',
    'stock_quantity',
    'low_stock_threshold',
    'unit',
    'is_taxable',
    'status',
    'is_featured',
    'is_suggested',
])]
class Product extends Model
{
    use CleansOfferTargets;
    use CleansPublicMedia;
    use HasFactory;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::updated(function (Product $product): void {
            if (! $product->wasChanged(['stock_quantity', 'low_stock_threshold'])) {
                return;
            }

            app(LowStockAlertService::class)->checkIfEnteredLowStock(
                product: $product,
                previousQuantity: (int) $product->getOriginal('stock_quantity'),
                previousThreshold: (int) $product->getOriginal('low_stock_threshold'),
            );
        });

        static::forceDeleting(function (Product $product): void {
            $product->images()->get()->each->delete();
        });
    }

    /**
     * @return list<string>
     */
    protected function publicMediaAttributes(): array
    {
        return ['image'];
    }

    protected function offerTargetType(): OfferTargetType
    {
        return OfferTargetType::Product;
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * @return HasMany<InventoryLog, $this>
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
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
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_taxable' => 'boolean',
            'is_featured' => 'boolean',
            'is_suggested' => 'boolean',
            'status' => CatalogStatus::class,
        ];
    }
}
