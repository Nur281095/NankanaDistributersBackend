<?php

namespace App\Filament\Support;

use App\Enums\OfferTargetType;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Product;

class OfferFormHelper
{
    public static function targetLabel(OfferTargetType $type, int $targetId): string
    {
        return match ($type) {
            OfferTargetType::Company => Company::query()->find($targetId)?->name ?? "Company #{$targetId}",
            OfferTargetType::Brand => Brand::query()->find($targetId)?->name ?? "Brand #{$targetId}",
            OfferTargetType::Product => Product::query()->find($targetId)?->name ?? "Product #{$targetId}",
        };
    }

    /**
     * @return array<int, string>
     */
    public static function targetOptions(OfferTargetType $type): array
    {
        return match ($type) {
            OfferTargetType::Company => Company::query()->orderBy('name')->pluck('name', 'id')->all(),
            OfferTargetType::Brand => Brand::query()->orderBy('name')->pluck('name', 'id')->all(),
            OfferTargetType::Product => Product::query()->orderBy('name')->pluck('name', 'id')->all(),
        };
    }
}
