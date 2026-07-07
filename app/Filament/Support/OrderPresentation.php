<?php

namespace App\Filament\Support;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

class OrderPresentation
{
    public static function orderStatusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Received => 'info',
            OrderStatus::Packed => 'warning',
            OrderStatus::OnWay => 'primary',
            OrderStatus::Delivered => 'success',
            OrderStatus::Cancelled => 'danger',
        };
    }

    public static function orderPaymentStatusColor(OrderPaymentStatus $status): string
    {
        return match ($status) {
            OrderPaymentStatus::Pending => 'warning',
            OrderPaymentStatus::Paid => 'success',
            OrderPaymentStatus::Failed => 'danger',
            OrderPaymentStatus::CodPending => 'info',
            OrderPaymentStatus::Refunded => 'gray',
        };
    }

    public static function paymentStatusColor(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Pending => 'warning',
            PaymentStatus::Paid => 'success',
            PaymentStatus::Failed => 'danger',
            PaymentStatus::Refunded => 'gray',
        };
    }

    public static function paymentMethodColor(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Cod => 'success',
            PaymentMethod::Jazzcash => 'info',
            PaymentMethod::Easypaisa => 'primary',
        };
    }
}
