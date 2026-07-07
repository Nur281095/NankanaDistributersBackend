<?php

namespace App\Filament\Resources\BrandResource\Pages;

use App\Filament\Concerns\NormalizesCatalogSlug;
use App\Filament\Resources\BrandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBrand extends CreateRecord
{
    use NormalizesCatalogSlug;

    protected static string $resource = BrandResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeSlug($data, 'brands');
    }
}
