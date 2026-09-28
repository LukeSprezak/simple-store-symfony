<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

final class CartNotActiveException extends \RuntimeException
{
    public function __construct(string $cartId)
    {
        parent::__construct(sprintf('Cart with ID "%s" is not active.', $cartId));
    }
}
