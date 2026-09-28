<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Order\Domain\Policy\CartLimits;
use App\Shared\Domain\Exception\ConflictException;

final class CartQuantityLimitExceededException extends \RuntimeException implements ConflictException
{
    public function __construct(string $productId)
    {
        parent::__construct(sprintf('A cart may contain at most %d units of product "%s".', CartLimits::MAX_QUANTITY_PER_PRODUCT, $productId));
    }
}
