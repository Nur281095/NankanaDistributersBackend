<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Actions\CancelOrderAction;
use App\Filament\Actions\MarkCodReceivedAction;
use App\Filament\Actions\UpdateOrderStatusAction;
use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MarkCodReceivedAction::make(),
            UpdateOrderStatusAction::make(),
            CancelOrderAction::make(),
            Actions\EditAction::make()
                ->label('Edit admin note'),
        ];
    }
}
