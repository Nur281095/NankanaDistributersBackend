<?php

namespace App\Filament\Support;

use App\Models\Product;

final class LowStockRow
{
    public const CLASS_NAME = 'fi-ta-record-low-stock';

    public static function classes(?Product $record): ?string
    {
        if ($record === null || ! $record->isLowStock()) {
            return null;
        }

        return self::CLASS_NAME;
    }
}
