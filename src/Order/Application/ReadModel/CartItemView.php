<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

final readonly class CartItemView
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $productName,
        public int $unitPriceInCents,
        public int $quantity,
        public int $totalAmountInCents,
    ) {
    }
}
