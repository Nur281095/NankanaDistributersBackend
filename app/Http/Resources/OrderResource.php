<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canCancel = $this->cancellation_deadline !== null
            && now()->lessThanOrEqualTo($this->cancellation_deadline)
            && $this->order_status?->value === 'received';

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'is_guest' => $this->is_guest,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'delivery_address' => $this->delivery_address,
            'city' => $this->city,
            'area' => $this->area,
            'subtotal' => $this->subtotal,
            'delivery_charges' => $this->delivery_charges,
            'discount_amount' => $this->discount_amount,
            'grand_total' => $this->grand_total,
            'payment_method' => $this->payment_method?->value,
            'payment_status' => $this->payment_status?->value,
            'order_status' => $this->order_status?->value,
            'notes' => $this->notes,
            'cancellation_deadline' => $this->cancellation_deadline?->toIso8601String(),
            'can_cancel' => $canCancel,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'status_logs' => OrderStatusLogResource::collection($this->whenLoaded('statusLogs')),
        ];
    }
}
