<?php

namespace App\Filament\Resources\HomeSectionResource\Pages;

use App\Enums\HomeSectionType;
use App\Filament\Resources\HomeSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHomeSection extends EditRecord
{
    protected static string $resource = HomeSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
        /** @var HomeSectionType|string|null $type */
        $type = $this->record->type;

        $typeValue = $type instanceof HomeSectionType ? $type->value : (string) $type;

        if ($typeValue !== HomeSectionType::ProductCollection->value) {
            $data['product_source'] = null;
            $data['product_limit'] = null;
        }

        return $data;
    }
}
