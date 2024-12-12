<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Repository;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Infrastructure\Doctrine\Entity\Order as EntityOrder;
use App\Order\Infrastructure\Transformer\OrderTransformer;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderTransformer $orderTransformer,
    ) {
    }

    public function find(string $id): ?Order
    {
        $entityOrder = $this->entityManager->getRepository(EntityOrder::class)->find($id);

        return $entityOrder ? $this->orderTransformer->toDomain($entityOrder) : null;
    }

    public function save(Order $order): void
    {
        $entityOrder = $this->entityManager->getRepository(EntityOrder::class)->find($order->getId()) ?? new EntityOrder($order->getId());
        $this->orderTransformer->fromDomain($order, $entityOrder);

        $this->entityManager->persist($entityOrder);
        $this->entityManager->flush();
    }
}
