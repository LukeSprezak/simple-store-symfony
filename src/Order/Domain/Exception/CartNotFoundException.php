<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundException;

class CartNotFoundException extends \RuntimeException implements NotFoundException
{
    public function __construct(string $cartId)
    {
        parent::__construct(sprintf('Cart with ID "%s" does not exist.', $cartId));
    }
}
