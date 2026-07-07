<?php

namespace App\Enums;

enum NotificationType: string
{
    case Order = 'order';
    case Offer = 'offer';
    case Admin = 'admin';
    case System = 'system';
    case LowStock = 'low_stock';
    case Payment = 'payment';
}
