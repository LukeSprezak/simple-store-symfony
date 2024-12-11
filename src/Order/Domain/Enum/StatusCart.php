<?php

declare(strict_types=1);

namespace App\Order\Domain\Enum;

enum StatusCart: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case ABANDONED = 'abandoned';
    case CONVERTED_TO_ORDER = 'converted_to_order';
}
