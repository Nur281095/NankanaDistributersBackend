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
     * Resolve the actor that changed the status (admin, customer, or null for system).
     */
    public function changer(): Admin|User|null
    {
        if ($this->changed_by === null) {
            return null;
        }

        return match ($this->changed_by_type) {
            ChangedByType::Admin => Admin::query()->withTrashed()->find($this->changed_by),
            ChangedByType::Customer => User::query()->withTrashed()->find($this->changed_by),
            ChangedByType::System => null,
        };
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
