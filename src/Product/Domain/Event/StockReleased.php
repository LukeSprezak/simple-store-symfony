<?php

declare(strict_types=1);

namespace App\Product\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class StockReleased implements DomainEvent
{
    public const string NAME = 'product.stock_released';

    public function eventName(): string
    {
        return self::NAME;
    }

    public function __construct(
        public string $productId,
        public int $quantity,
    ) {
    }
}
