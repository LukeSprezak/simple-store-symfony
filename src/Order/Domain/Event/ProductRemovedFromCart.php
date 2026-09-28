<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class ProductRemovedFromCart implements DomainEvent
{
    public const string NAME = 'cart.product_removed';

    public function eventName(): string
    {
        return self::NAME;
    }

    public function __construct(
        public string $cartId,
        public string $productId,
        public int $quantity,
    ) {
    }
}
