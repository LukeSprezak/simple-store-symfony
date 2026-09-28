<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

final readonly class CartView
{
    /**
     * @param list<CartItemView> $items
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $createdAt,
        public string $expiresAt,
        public array $items,
        public int $totalAmountInCents,
    ) {
    }
}
