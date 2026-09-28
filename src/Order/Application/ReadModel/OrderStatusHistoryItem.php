<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

final readonly class OrderStatusHistoryItem
{
    public function __construct(
        public string $eventId,
        public string $transition,
        public string $fromStatus,
        public string $toStatus,
        public string $recordedAt,
    ) {
    }
}
