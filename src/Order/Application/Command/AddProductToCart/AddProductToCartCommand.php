<?php

declare(strict_types=1);

namespace App\Order\Application\Command\AddProductToCart;

use App\Order\Domain\Enum\StatusCart;
use App\Shared\Application\Bus\Command\Sync\Command;
use App\User\Domain\ValueObject\UserId;

final readonly class AddProductToCartCommand implements Command
{
    public function __construct(
        public string $cartId,
        public string $productId,
        public int $quantity,
        public StatusCart $status,
        public UserId $userId,
        public bool $createCart,
    ) {
    }
}
