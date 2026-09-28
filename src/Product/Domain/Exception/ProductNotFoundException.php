<?php

declare(strict_types=1);

namespace App\Product\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundException;

final class ProductNotFoundException extends \RuntimeException implements NotFoundException
{
    public function __construct(string $productId)
    {
        parent::__construct(sprintf('Product with ID "%s" does not exist.', $productId));
    }
}
