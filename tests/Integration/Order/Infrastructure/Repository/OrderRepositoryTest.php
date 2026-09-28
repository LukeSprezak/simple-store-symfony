<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\Infrastructure\Repository;

use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Enum\StatusOrderTransition;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderItem;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Infrastructure\Repository\OrderRepository;
use App\Product\Domain\Model\Product;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use EventSauce\EventSourcing\UnableToPersistMessages;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class OrderRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private OrderRepository $repository;
    private Product $product;
    private Order $order;
    private OrderItem $item;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();
        $this->repository = self::getContainer()->get(OrderRepository::class);
        $this->connection->beginTransaction();

        $ownerId = UserId::generate();
        $this->product = Product::create(Uuid::v7()->toRfc4122(), 'Product', 'Description', new Money(2500), 10, $ownerId);
        $this->entityManager->persist($this->product);
        $this->entityManager->flush();

        $this->item = OrderItem::create(Uuid::v7()->toRfc4122(), new ProductSnapshot($this->product->getId(), 'Product', new Money(2500)), 2);
        $this->order = Order::create(Uuid::v7()->toRfc4122(), StatusOrder::CREATED->value, $ownerId, new \DateTimeImmutable('2026-09-28 12:00:00'), [$this->item]);
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $this->entityManager->clear();
        }

        parent::tearDown();
    }

    #[Test]
    public function rebuildsTheOrderFromItsStreamWithTheFrozenPrice(): void
    {
        $this->repository->save($this->order);
        $this->connection->update('product', ['price' => 9999, 'name' => 'Renamed'], ['id' => $this->product->getId()]);

        $loadedOrder = $this->repository->find($this->order->getId());
        self::assertNotNull($loadedOrder);
        self::assertSame(1, $loadedOrder->aggregateRootVersion());
        self::assertTrue($this->order->getOwnerId()->equals($loadedOrder->getOwnerId()));
        self::assertEquals($this->order->getCreatedAt(), $loadedOrder->getCreatedAt());
        self::assertSame(StatusOrder::CREATED->value, $loadedOrder->getStatus());
        self::assertSame([], $loadedOrder->releaseEvents());
        self::assertCount(1, $loadedOrder->getItems());
        $loadedItem = $loadedOrder->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $loadedItem);
        self::assertSame($this->item->getId(), $loadedItem->getId());
        self::assertSame($this->product->getId(), $loadedItem->getProduct()->getId());
        self::assertSame('Product', $loadedItem->getProduct()->getName());
        self::assertSame(2500, $loadedItem->getProduct()->getPrice()->getAmount());
        self::assertSame(2, $loadedItem->getQuantity());
    }

    #[Test]
    public function projectsTheOrderTablesAndQueuesEventsInTheOutbox(): void
    {
        $this->repository->save($this->order);
        $this->entityManager->flush();

        self::assertSame(
            ['status' => StatusOrder::CREATED->value, 'owner_id' => $this->order->getOwnerId()->getId(), 'created_at' => '2026-09-28 12:00:00', 'updated_at' => null],
            $this->connection->fetchAssociative('SELECT status, owner_id, created_at, updated_at FROM `order` WHERE id = :id', ['id' => $this->order->getId()])
        );
        self::assertEquals(
            [['id' => $this->item->getId(), 'product_id' => $this->product->getId(), 'quantity' => 2, 'unit_price' => 2500]],
            $this->connection->fetchAllAssociative('SELECT id, product_id, quantity, unit_price FROM order_item WHERE order_id = :id', ['id' => $this->order->getId()])
        );

        $loadedOrder = $this->repository->find($this->order->getId());
        self::assertNotNull($loadedOrder);
        $loadedOrder->changeStatus(StatusOrderTransition::PAY);
        $this->repository->save($loadedOrder);
        $this->entityManager->flush();

        $reloadedOrder = $this->repository->find($this->order->getId());
        self::assertNotNull($reloadedOrder);
        self::assertSame(StatusOrder::PENDING_PAYMENT->value, $reloadedOrder->getStatus());
        self::assertSame(2, $reloadedOrder->aggregateRootVersion());
        self::assertSame(StatusOrder::PENDING_PAYMENT->value, $this->connection->fetchOne('SELECT status FROM `order` WHERE id = :id', ['id' => $this->order->getId()]));
        self::assertNotNull($this->connection->fetchOne('SELECT updated_at FROM `order` WHERE id = :id', ['id' => $this->order->getId()]));

        /** @var list<array{event_name: string, payload: string}> $outbox */
        $outbox = $this->connection->fetchAllAssociative(
            "SELECT event_name, payload FROM domain_event_outbox WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.orderId')) = :id ORDER BY id",
            ['id' => $this->order->getId()]
        );
        self::assertSame([OrderPlaced::NAME, OrderStatusChanged::NAME], array_column($outbox, 'event_name'));
        self::assertSame(
            ['orderId' => $this->order->getId(), 'transition' => 'pay', 'fromStatus' => 'created', 'toStatus' => 'pending_payment'],
            json_decode($outbox[1]['payload'], true)
        );
    }

    #[Test]
    public function returnsNullForAnUnknownStream(): void
    {
        self::assertNull($this->repository->find(Uuid::v7()->toRfc4122()));
    }

    #[Test]
    public function rejectsAStaleCopyOfTheStream(): void
    {
        $this->repository->save($this->order);
        $first = $this->repository->find($this->order->getId());
        $second = $this->repository->find($this->order->getId());
        self::assertNotNull($first);
        self::assertNotNull($second);

        $first->changeStatus(StatusOrderTransition::PAY);
        $this->repository->save($first);
        $second->changeStatus(StatusOrderTransition::CANCEL);

        $this->expectException(UnableToPersistMessages::class);

        $this->repository->save($second);
    }
}
