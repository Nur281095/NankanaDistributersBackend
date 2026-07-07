<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Jazzcash = 'jazzcash';
    case Easypaisa = 'easypaisa';
}
