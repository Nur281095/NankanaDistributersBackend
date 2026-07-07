<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Received = 'received';
    case Packed = 'packed';
    case OnWay = 'on_way';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
