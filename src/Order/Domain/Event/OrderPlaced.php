<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class OrderPlaced implements DomainEvent
{
    public const string NAME = 'order.placed';

    public function eventName(): string
    {
        return self::NAME;
    }

    public function __construct(
        public string $orderId,
        public string $ownerId,
    ) {
    }
}
