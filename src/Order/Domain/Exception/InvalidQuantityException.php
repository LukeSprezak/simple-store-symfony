<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Domain\Exception\ConflictException;

final class InvalidQuantityException extends \InvalidArgumentException implements ConflictException
{
    public function __construct(string $message = 'Incorrect quantity.')
    {
        parent::__construct($message);
    }
}
