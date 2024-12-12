<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

class CartNotFoundException extends \RuntimeException
{
    public function __construct(string $cartId)
    {
        parent::__construct(sprintf('Cart with ID "%s" does not exist.', $cartId));
    }
}
