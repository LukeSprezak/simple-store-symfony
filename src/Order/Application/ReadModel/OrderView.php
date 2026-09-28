<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

final readonly class OrderView
{
    public function __construct(
        public string $id,
        public string $status,
        public string $createdAt,
        public int $totalAmountInCents,
    ) {
    }
}
