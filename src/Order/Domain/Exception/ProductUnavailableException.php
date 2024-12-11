<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

final class ProductUnavailableException extends \RuntimeException
{
    public function __construct(string $productId)
    {
        parent::__construct(sprintf('The product with the ID "%s" is not available.', $productId));
    }
}
