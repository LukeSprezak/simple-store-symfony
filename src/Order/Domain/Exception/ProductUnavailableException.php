<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Domain\Exception\ConflictException;

final class ProductUnavailableException extends \RuntimeException implements ConflictException
{
    public function __construct(string $productId)
    {
        parent::__construct(sprintf('The product with the ID "%s" is not available.', $productId));
    }
}
