<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Actions\CancelOrderAction;
use App\Filament\Actions\MarkCodReceivedAction;
use App\Filament\Actions\UpdateOrderStatusAction;
use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MarkCodReceivedAction::make(),
            UpdateOrderStatusAction::make(),
            CancelOrderAction::make(),
            Actions\ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
