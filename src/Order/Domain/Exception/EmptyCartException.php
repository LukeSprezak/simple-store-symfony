<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Domain\Exception\ConflictException;

final class EmptyCartException extends \RuntimeException implements ConflictException
{
    public function __construct(string $cartId)
    {
        parent::__construct(sprintf('Cart with ID "%s" is empty.', $cartId));
    }
}
