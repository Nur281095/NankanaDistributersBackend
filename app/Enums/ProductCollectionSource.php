<?php

namespace App\Enums;

enum ProductCollectionSource: string
{
    case NewArrivals = 'new_arrivals';
    case OnSale = 'on_sale';
    case TopSelling = 'top_selling';
    case Featured = 'featured';
    case Manual = 'manual';
}
