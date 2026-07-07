<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Actions\AdjustStockAction;
use App\Filament\Concerns\NormalizesCatalogSlug;
use App\Filament\Resources\ProductResource;
use App\Models\Brand;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditProduct extends EditRecord
{
    use NormalizesCatalogSlug;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdjustStockAction::make(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
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
