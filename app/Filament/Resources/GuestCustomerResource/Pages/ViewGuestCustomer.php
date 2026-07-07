<?php

namespace App\Filament\Resources\GuestCustomerResource\Pages;

use App\Filament\Resources\GuestCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGuestCustomer extends ViewRecord
{
    protected static string $resource = GuestCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
