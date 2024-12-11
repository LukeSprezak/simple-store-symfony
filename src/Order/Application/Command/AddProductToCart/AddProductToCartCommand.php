<?php

declare(strict_types=1);

namespace App\Order\Application\Command\AddProductToCart;

use App\Shared\Application\Bus\Command\Sync\Command;

final readonly class AddProductToCartCommand implements Command
{
    public function __construct(
        public string $cartId,
        public string $productId,
        public int $quantity,
    ) {
    }
}
