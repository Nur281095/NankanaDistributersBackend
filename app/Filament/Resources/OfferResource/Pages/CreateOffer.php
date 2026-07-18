<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Exceptions\BusinessException;
use App\Filament\Resources\OfferResource;
use App\Services\OfferService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateOffer extends CreateRecord
{
    protected static string $resource = OfferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            app(OfferService::class)->validate($data);
        } catch (BusinessException $exception) {
            throw ValidationException::withMessages([
                'discount_value' => [$exception->getMessage()],
            ]);
        }

        return $data;
    }
}
