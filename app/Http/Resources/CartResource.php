<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Cart */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subtotal = '0.00';

        if ($this->relationLoaded('items')) {
            $subtotal = $this->items->reduce(
                fn (string $carry, $item): string => bcadd(
                    $carry,
                    bcmul((string) $item->price, (string) $item->quantity, 2),
                    2,
                ),
                '0.00',
            );
        }

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'subtotal' => $subtotal,
        ];
    }
}
