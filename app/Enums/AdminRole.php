<?php

namespace App\Enums;

enum AdminRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case InventoryStaff = 'inventory_staff';
    case DeliveryStaff = 'delivery_staff';
    case SalesStaff = 'sales_staff';
}
