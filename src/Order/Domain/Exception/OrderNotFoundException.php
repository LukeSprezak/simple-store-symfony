<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundException;

class OrderNotFoundException extends \RuntimeException implements NotFoundException
{
    public function __construct(string $orderId)
    {
        parent::__construct(sprintf('Order with ID "%s" does not exist.', $orderId));
    }
}
