<?php

namespace App\Filament\Resources\InventoryLogResource\Pages;

use App\Filament\Resources\InventoryLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryLog extends ViewRecord
{
    protected static string $resource = InventoryLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
