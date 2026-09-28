<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Repository;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Application\Bus\Event\EventBus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventBus $eventBus,
    ) {
    }

    public function find(string $id): ?Order
    {
        return $this->entityManager->find(Order::class, $id);
    }

    public function save(Order $order): void
    {
        $this->entityManager->persist($order);

        $this->eventBus->publish(...$order->pullDomainEvents());
    }
}
