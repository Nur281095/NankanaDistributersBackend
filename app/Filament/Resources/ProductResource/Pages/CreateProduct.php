<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Concerns\NormalizesCatalogSlug;
use App\Filament\Resources\ProductResource;
use App\Models\Brand;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProduct extends CreateRecord
{
    use NormalizesCatalogSlug;

    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->assertBrandBelongsToCompany($data);

        return $this->normalizeSlug($data, 'products');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertBrandBelongsToCompany(array $data): void
    {
        $brand = Brand::query()->find($data['brand_id'] ?? null);

        if ($brand === null || (int) $brand->company_id !== (int) ($data['company_id'] ?? 0)) {
            throw ValidationException::withMessages([
                'brand_id' => 'The selected brand does not belong to the chosen company.',
            ]);
        }
    }
}
