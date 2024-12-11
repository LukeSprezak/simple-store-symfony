<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

class ProductNotInCartException extends \RuntimeException
{
    public function __construct(string $productId)
    {
        parent::__construct(sprintf('Product with ID "%s" is not in the cart.', $productId));
    }
}
