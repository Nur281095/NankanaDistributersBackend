<?php

namespace App\Enums;

enum ChangedByType: string
{
    case Admin = 'admin';
    case Customer = 'customer';
    case System = 'system';
}
