<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

final readonly class CartActivityItem
{
    public function __construct(
        public string $eventId,
        public string $eventName,
        public string $recordedAt,
        public ?string $productId,
        public ?int $quantity,
    ) {
    }
}
