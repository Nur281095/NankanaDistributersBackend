<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Concerns\NormalizesCatalogSlug;
use App\Filament\Resources\CompanyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    use NormalizesCatalogSlug;

    protected static string $resource = CompanyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeSlug($data, 'companies');
    }
}
