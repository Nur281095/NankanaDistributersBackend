<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Services\PaymentReportService;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getSubheading(): ?string
    {
        $summary = app(PaymentReportService::class)->summary();

        return sprintf(
            'Paid: %d · Pending: %d · Failed: %d · Total collected: PKR %s',
            $summary['paid_count'],
            $summary['pending_count'],
            $summary['failed_count'],
            number_format((float) $summary['paid_amount'], 2),
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
