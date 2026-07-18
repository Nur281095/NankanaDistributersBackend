<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicStorageCleanup
{
    public static function deleteIfExists(?string $path): void
    {
        if ($path === null || $path === '' || str_contains($path, '://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public static function deleteReplaced(?string $original, ?string $updated): void
    {
        if ($original === null || $original === '' || $original === $updated) {
            return;
        }

        self::deleteIfExists($original);
    }
}
