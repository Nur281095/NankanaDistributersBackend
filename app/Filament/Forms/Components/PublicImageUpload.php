<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Shared public-disk image upload for Filament.
 *
 * FilePond's default "Waiting for size" state hangs when it cannot probe the
 * remote file (subdomain admin, CORS, or size=0 from fetchFileInformation(false)).
 * We hydrate name/size/mime/url from the local public disk instead.
 */
final class PublicImageUpload
{
    public static function make(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->disk('public')
            ->visibility('public')
            ->fetchFileInformation(false)
            ->imagePreviewHeight('160')
            ->openable()
            ->downloadable()
            ->maxSize(4096)
            ->getUploadedFileUsing(function (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                $disk = $component->getDiskName() ?: 'public';
                $storage = Storage::disk($disk);

                try {
                    if (! $storage->exists($file)) {
                        return null;
                    }
                } catch (Throwable) {
                    // Shared hosts can throw on exists(); still try to build a preview.
                }

                $absolutePath = null;

                try {
                    $absolutePath = $storage->path($file);
                } catch (Throwable) {
                    $absolutePath = null;
                }

                $size = (is_string($absolutePath) && is_file($absolutePath))
                    ? (int) filesize($absolutePath)
                    : 0;

                $mime = (is_string($absolutePath) && is_file($absolutePath))
                    ? (mime_content_type($absolutePath) ?: null)
                    : null;

                if ($mime === null) {
                    $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        'svg' => 'image/svg+xml',
                        default => 'application/octet-stream',
                    };
                }

                // Same-origin absolute URL for the current admin host (not APP_URL).
                $url = url('/storage/'.ltrim(str_replace('\\', '/', $file), '/'));

                $displayName = is_array($storedFileNames)
                    ? ($storedFileNames[$file] ?? basename($file))
                    : ($storedFileNames ?: basename($file));

                return [
                    'name' => $displayName,
                    'size' => $size > 0 ? $size : 1,
                    'type' => $mime,
                    'url' => $url,
                ];
            });
    }
}
