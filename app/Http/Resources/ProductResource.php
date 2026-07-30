<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku_code' => $this->sku_code,
            'image' => $this->image,
            'regular_price' => $this->regular_price,
            'sale_price' => $this->sale_price,
            'stock_quantity' => $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
            'unit' => $this->unit,
            'status' => $this->status?->value,
            'is_featured' => $this->is_featured,
            'is_suggested' => $this->is_suggested,
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'company' => CompanyResource::make($this->whenLoaded('company')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
