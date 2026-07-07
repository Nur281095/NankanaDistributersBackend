<?php

namespace App\Enums;

enum InventoryLogType: string
{
    case Added = 'added';
    case Removed = 'removed';
    case OrderPlaced = 'order_placed';
    case OrderCancelled = 'order_cancelled';
    case ManualAdjustment = 'manual_adjustment';
    case Damaged = 'damaged';
    case Returned = 'returned';
}
