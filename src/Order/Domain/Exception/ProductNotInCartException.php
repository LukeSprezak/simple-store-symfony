<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundException;

class ProductNotInCartException extends \RuntimeException implements NotFoundException
{
    public function __construct(string $productId)
    {
        parent::__construct(sprintf('Product with ID "%s" is not in the cart.', $productId));
    }
}
