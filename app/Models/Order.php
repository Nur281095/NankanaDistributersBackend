<?php

namespace App\Models;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number',
    'user_id',
    'guest_customer_id',
    'is_guest',
    'customer_name',
    'customer_phone',
    'customer_email',
    'delivery_address',
    'city',
    'area',
    'subtotal',
    'delivery_charges',
    'discount_amount',
    'grand_total',
    'payment_method',
    'payment_status',
    'order_status',
    'notes',
    'admin_note',
    'cancellation_deadline',
    'cancelled_at',
    'delivered_at',
])]
class Order extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<GuestCustomer, $this>
     */
    public function guestCustomer(): BelongsTo
    {
        return $this->belongsTo(GuestCustomer::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_guest' => 'boolean',
            'subtotal' => 'decimal:2',
            'delivery_charges' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'payment_status' => OrderPaymentStatus::class,
            'order_status' => OrderStatus::class,
            'cancellation_deadline' => 'datetime',
            'cancelled_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
