<?php

declare(strict_types=1);

namespace App\Order\Domain\Policy;

final class CartLimits
{
    public const int MAX_QUANTITY_PER_PRODUCT = 10;
    public const int MAX_ACTIVE_CARTS_PER_OWNER = 3;
}
