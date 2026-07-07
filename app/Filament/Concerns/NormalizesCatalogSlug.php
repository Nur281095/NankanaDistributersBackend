<?php

namespace App\Filament\Concerns;

use App\Filament\Support\CatalogFormHelper;

trait NormalizesCatalogSlug
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeSlug(array $data, string $table): array
    {
        $ignoreId = property_exists($this, 'record') && isset($this->record)
            ? $this->record->getKey()
            : null;

        $source = $data['slug'] ?? $data['name'] ?? '';

        if (is_string($source) && $source !== '') {
            $data['slug'] = CatalogFormHelper::uniqueSlug($source, $table, $ignoreId);
        }

        return $data;
    }
}
