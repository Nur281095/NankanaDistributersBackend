<?php

namespace App\Filament\Resources\GuestCustomerResource\Pages;

use App\Filament\Resources\GuestCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGuestCustomer extends CreateRecord
{
    protected static string $resource = GuestCustomerResource::class;
}
