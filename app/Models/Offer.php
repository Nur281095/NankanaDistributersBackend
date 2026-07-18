<?php

namespace App\Models;

use App\Enums\CatalogStatus;
use App\Enums\DiscountType;
use App\Models\Concerns\CleansPublicMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title',
    'description',
    'image',
    'discount_type',
    'discount_value',
    'start_date',
    'end_date',
    'status',
])]
class Offer extends Model
{
    use CleansPublicMedia;
    use SoftDeletes;

    /**
     * @return list<string>
     */
    protected function publicMediaAttributes(): array
    {
        return ['image'];
    }

    /**
     * @return HasMany<OfferTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(OfferTarget::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => CatalogStatus::class,
        ];
    }
}
