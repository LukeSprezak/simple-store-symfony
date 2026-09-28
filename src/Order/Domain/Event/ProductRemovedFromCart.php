<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class ProductRemovedFromCart implements DomainEvent
{
    public function __construct(
        public string $cartId,
        public string $productId,
        public int $quantity,
    ) {
    }
}
