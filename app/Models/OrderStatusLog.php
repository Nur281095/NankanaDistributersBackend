<?php

namespace App\Models;

use App\Enums\ChangedByType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'old_status',
    'new_status',
    'changed_by',
    'changed_by_type',
    'note',
])]
class OrderStatusLog extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_status' => OrderStatus::class,
            'new_status' => OrderStatus::class,
            'changed_by' => 'integer',
            'changed_by_type' => ChangedByType::class,
        ];
    }
}
