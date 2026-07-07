<?php

namespace App\Models;

use App\Enums\InventoryLogType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'admin_id',
    'type',
    'old_quantity',
    'new_quantity',
    'quantity_difference',
    'reference_type',
    'reference_id',
    'note',
])]
class InventoryLog extends Model
{
    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InventoryLogType::class,
            'old_quantity' => 'integer',
            'new_quantity' => 'integer',
            'quantity_difference' => 'integer',
            'reference_id' => 'integer',
        ];
    }
}
