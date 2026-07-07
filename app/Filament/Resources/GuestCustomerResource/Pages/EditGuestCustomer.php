<?php

namespace App\Filament\Resources\GuestCustomerResource\Pages;

use App\Filament\Resources\GuestCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGuestCustomer extends EditRecord
{
    protected static string $resource = GuestCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
