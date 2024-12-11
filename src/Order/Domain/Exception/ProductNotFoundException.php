<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

final class ProductNotFoundException extends \RuntimeException
{
    public function __construct(string $productId)
    {
        parent::__construct(sprintf('Product with ID "%s" does not exist.', $productId));
    }
}
