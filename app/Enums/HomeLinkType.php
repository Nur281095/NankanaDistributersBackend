<?php

namespace App\Enums;

enum HomeLinkType: string
{
    case None = 'none';
    case Product = 'product';
    case Brand = 'brand';
    case Company = 'company';
    case Offer = 'offer';
    case Url = 'url';
}
