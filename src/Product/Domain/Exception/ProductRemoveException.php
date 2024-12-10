<?php

declare(strict_types=1);

namespace App\Product\Domain\Exception;

final class ProductRemoveException extends \Exception
{
    public function __construct(string $message = 'An error occurred while removing the product.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
