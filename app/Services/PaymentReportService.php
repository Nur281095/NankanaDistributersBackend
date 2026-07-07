<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class PaymentReportService
{
    /**
     * @return array{
     *     total_count: int,
     *     paid_count: int,
     *     failed_count: int,
     *     pending_count: int,
     *     paid_amount: string
     * }
     */
    public function summary(?Carbon $from = null, ?Carbon $until = null): array
    {
        $query = Payment::query();

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from->toDateString());
        }

        if ($until !== null) {
            $query->whereDate('created_at', '<=', $until->toDateString());
        }

        $payments = $query->get(['payment_status', 'amount']);

        $paidAmount = $payments
            ->where('payment_status', PaymentStatus::Paid)
            ->sum(fn (Payment $payment): float => (float) $payment->amount);

        return [
            'total_count' => $payments->count(),
            'paid_count' => $payments->where('payment_status', PaymentStatus::Paid)->count(),
            'failed_count' => $payments->where('payment_status', PaymentStatus::Failed)->count(),
            'pending_count' => $payments->where('payment_status', PaymentStatus::Pending)->count(),
            'paid_amount' => number_format($paidAmount, 2, '.', ''),
        ];
    }
}
