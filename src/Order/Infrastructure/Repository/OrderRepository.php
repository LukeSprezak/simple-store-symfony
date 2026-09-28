<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Repository;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\ValueObject\OrderId;
use App\Order\Infrastructure\EventSourcing\OrderTableProjector;
use App\Order\Infrastructure\EventSourcing\OutboxRelay;
use App\Shared\Application\Bus\Event\EventBus;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\IdEncoding\StringIdEncoder;
use EventSauce\MessageRepository\DoctrineMessageRepository\DoctrineMessageRepository;

final readonly class OrderRepository implements OrderRepositoryInterface
{
    /** @var EventSourcedAggregateRootRepository<Order> */
    private EventSourcedAggregateRootRepository $repository;

    public function __construct(Connection $connection, EventBus $eventBus)
    {
        $this->repository = new EventSourcedAggregateRootRepository(
            Order::class,
            new DoctrineMessageRepository($connection, 'order_event', new ConstructingMessageSerializer(), aggregateRootIdEncoder: new StringIdEncoder()),
            new SynchronousMessageDispatcher(new OrderTableProjector($connection), new OutboxRelay($eventBus)),
        );
    }

    public function find(string $id): ?Order
    {
        $order = $this->repository->retrieve(OrderId::fromString($id));

        // EventSauce returns an empty aggregate for an unknown stream.
        return 0 === $order->aggregateRootVersion() ? null : $order;
    }

    public function save(Order $order): void
    {
        $this->repository->persist($order);
    }
}
