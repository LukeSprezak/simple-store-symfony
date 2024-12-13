<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

class ProductRemoveFromCartException extends \RuntimeException
{
    public function __construct(string $productId, string $message = 'Failed to remove product from cart.')
    {
        parent::__construct(sprintf('%s Product ID: "%s".', $message, $productId));
    }
}
