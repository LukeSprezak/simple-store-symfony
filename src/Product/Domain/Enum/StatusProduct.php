<?php

declare(strict_types=1);

namespace App\Product\Domain\Enum;

enum StatusProduct: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SOLD_OUT = 'sold_out';
}
