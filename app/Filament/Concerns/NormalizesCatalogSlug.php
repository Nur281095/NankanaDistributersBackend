<?php

namespace App\Filament\Concerns;

use App\Filament\Support\CatalogFormHelper;

trait NormalizesCatalogSlug
{
    /**
     * Keep slug out of admin forms while still satisfying DB uniqueness.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeSlug(array $data, string $table): array
    {
        $ignoreId = property_exists($this, 'record') && isset($this->record)
            ? $this->record->getKey()
            : null;

        // Updates: preserve the existing slug (forms no longer collect it).
        if ($ignoreId !== null && isset($this->record) && filled($this->record->getAttribute('slug'))) {
            $data['slug'] = (string) $this->record->getAttribute('slug');

            return $data;
        }

        $source = $data['name'] ?? '';

        if (is_string($source) && $source !== '') {
            $data['slug'] = CatalogFormHelper::uniqueSlug($source, $table, $ignoreId);
        }

        return $data;
    }
}
