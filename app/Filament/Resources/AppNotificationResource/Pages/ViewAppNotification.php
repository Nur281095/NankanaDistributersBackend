<?php

namespace App\Filament\Resources\AppNotificationResource\Pages;

use App\Filament\Actions\MarkNotificationReadAction;
use App\Filament\Resources\AppNotificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAppNotification extends ViewRecord
{
    protected static string $resource = AppNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MarkNotificationReadAction::make(),
        ];
    }
}
