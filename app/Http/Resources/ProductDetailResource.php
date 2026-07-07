<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** @mixin \App\Models\Product */
class ProductDetailResource extends ProductResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'low_stock_threshold' => $this->low_stock_threshold,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
        ]);
    }
}
