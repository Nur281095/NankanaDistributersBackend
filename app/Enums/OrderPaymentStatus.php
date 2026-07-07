<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case CodPending = 'cod_pending';
    case Refunded = 'refunded';
}
