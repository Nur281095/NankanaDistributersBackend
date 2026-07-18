<?php

namespace App\Filament\Resources\HomeSectionResource\Pages;

use App\Enums\HomeSectionType;
use App\Filament\Resources\HomeSectionResource;
use App\Models\HomeSection;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeSection extends CreateRecord
{
    protected static string $resource = HomeSectionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['type'] ?? null) !== HomeSectionType::ProductCollection->value) {
            $data['product_source'] = null;
            $data['product_limit'] = null;
        }

        if (($data['type'] ?? null) === HomeSectionType::ProductCollection->value) {
            $data['product_limit'] = $data['product_limit'] ?? 10;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var HomeSection $section */
        $section = $this->record;

        if ($section->type === HomeSectionType::Slider && $section->slider === null) {
            $section->slider()->create([
                'autoplay' => true,
                'interval_ms' => 4000,
            ]);
        }
    }
}
