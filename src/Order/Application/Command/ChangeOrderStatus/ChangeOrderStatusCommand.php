<?php

declare(strict_types=1);

namespace App\Order\Application\Command\ChangeOrderStatus;

use App\Order\Domain\Enum\StatusOrderTransition;
use App\Shared\Application\Bus\Command\Sync\Command;

final readonly class ChangeOrderStatusCommand implements Command
{
    /**
     * @param non-empty-string $orderId
     */
    public function __construct(
        public string $orderId,
        public StatusOrderTransition $transition,
    ) {
    }
}
