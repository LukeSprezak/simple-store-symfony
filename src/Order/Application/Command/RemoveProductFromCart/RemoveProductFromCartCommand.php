<?php

declare(strict_types=1);

namespace App\Order\Application\Command\RemoveProductFromCart;

use App\Shared\Application\Bus\Command\Sync\Command;
use App\User\Domain\ValueObject\UserId;

final readonly class RemoveProductFromCartCommand implements Command
{
    public function __construct(
        public string $cartId,
        public string $productId,
        public UserId $userId,
    ) {
    }
}
