<?php

namespace App\Filament\Support;

use Illuminate\Support\Str;

class CatalogFormHelper
{
    public static function uniqueSlug(string $value, string $table, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value);
        $original = $slug;
        $counter = 1;

        while (self::slugExists($slug, $table, $ignoreId)) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private static function slugExists(string $slug, string $table, ?int $ignoreId): bool
    {
        $query = \Illuminate\Support\Facades\DB::table($table)->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
