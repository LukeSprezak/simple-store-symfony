<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Request;

use App\Order\Application\Command\ChangeOrderStatus\ChangeOrderStatusCommand;
use App\Order\Domain\Enum\StatusOrderTransition;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangeOrderStatusRequest
{
    #[Assert\NotBlank]
    public StatusOrderTransition $transition;

    /**
     * @param non-empty-string $orderId
     */
    public function toCommand(string $orderId): ChangeOrderStatusCommand
    {
        return new ChangeOrderStatusCommand($orderId, $this->transition);
    }
}
