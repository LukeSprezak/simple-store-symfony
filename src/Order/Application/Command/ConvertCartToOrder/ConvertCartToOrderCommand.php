<?php

declare(strict_types=1);

namespace App\Order\Application\Command\ConvertCartToOrder;

use App\Shared\Application\Bus\Command\Sync\Command;
use App\User\Domain\ValueObject\UserId;

final readonly class ConvertCartToOrderCommand implements Command
{
    public function __construct(
        public string $cartId,
        public UserId $userId,
    ) {
    }
}
