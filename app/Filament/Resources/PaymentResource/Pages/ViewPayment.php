<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewOrder')
                ->label('View order')
                ->icon('heroicon-o-shopping-cart')
                ->url(fn (): string => OrderResource::getUrl('view', ['record' => $this->record->order_id])),
        ];
    }
}
