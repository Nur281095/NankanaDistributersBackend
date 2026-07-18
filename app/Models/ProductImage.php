<?php

namespace App\Models;

use App\Models\Concerns\CleansPublicMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'image_url', 'sort_order'])]
class ProductImage extends Model
{
    use CleansPublicMedia;

    /**
     * @return list<string>
     */
    protected function publicMediaAttributes(): array
    {
        return ['image_url'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
