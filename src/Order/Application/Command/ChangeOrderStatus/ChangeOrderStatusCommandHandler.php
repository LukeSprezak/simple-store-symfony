<?php

declare(strict_types=1);

namespace App\Order\Application\Command\ChangeOrderStatus;

use App\Order\Domain\Exception\OrderNotFoundException;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Application\Bus\Command\Sync\CommandHandler;

final readonly class ChangeOrderStatusCommandHandler implements CommandHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function __invoke(ChangeOrderStatusCommand $command): void
    {
        $order = $this->orderRepository->find($command->orderId)
            ?? throw new OrderNotFoundException($command->orderId);

        $order->changeStatus($command->transition);

        $this->orderRepository->save($order);
    }
}
