<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\Infrastructure\Repository;

use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderItem;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Infrastructure\Doctrine\Entity\Order as EntityOrder;
use App\Order\Infrastructure\Doctrine\Entity\OrderItem as EntityOrderItem;
use App\Order\Infrastructure\Repository\OrderRepository;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Product\Domain\Model\Product;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class OrderRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private OrderRepository $repository;
    private Product $product;
    private Order $order;
    private OrderItem $item;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(OrderRepository::class);
        $this->entityManager->getConnection()->beginTransaction();

        $ownerId = UserId::generate();
        $this->product = Product::create(Uuid::v7()->toRfc4122(), 'Product', 'Description', new Money(2500), 10, $ownerId);
        $this->entityManager->persist($this->product);
        $this->entityManager->flush();

        $this->order = Order::create(Uuid::v7()->toRfc4122(), StatusOrder::CREATED->value, $ownerId, new \DateTimeImmutable('2026-09-28 12:00:00'));
        $this->item = OrderItem::create(Uuid::v7()->toRfc4122(), new ProductSnapshot($this->product->getId(), 'Product', new Money(2500)), 2);
        $this->order->addItem($this->item);
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $connection = $this->entityManager->getConnection();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();
        }

        parent::tearDown();
    }

    #[Test]
    public function roundTripPreservesTheSnapshotAndDeletesRemovedItems(): void
    {
        $this->repository->save($this->order);
        $this->entityManager->flush();
        $this->entityManager->getConnection()->update('product', ['price' => 9999], ['id' => $this->product->getId()]);
        $this->entityManager->clear();

        $loadedOrder = $this->repository->find($this->order->getId());
        self::assertNotNull($loadedOrder);
        self::assertTrue($this->order->getOwnerId()->equals($loadedOrder->getOwnerId()));
        self::assertEquals($this->order->getCreatedAt(), $loadedOrder->getCreatedAt());
        self::assertSame(StatusOrder::CREATED->value, $loadedOrder->getStatus());
        self::assertSame([], $loadedOrder->pullDomainEvents());
        self::assertCount(1, $loadedOrder->getItems());
        $loadedItem = $loadedOrder->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $loadedItem);
        self::assertSame($this->item->getId(), $loadedItem->getId());
        self::assertSame($this->product->getId(), $loadedItem->getProduct()->getId());
        self::assertSame(2500, $loadedItem->getProduct()->getPrice()->getAmount());
        self::assertSame(2, $loadedItem->getQuantity());

        // Saving an existing item must also preserve its frozen price.
        $this->repository->save($loadedOrder);
        $this->entityManager->flush();
        $entityItem = $this->entityManager->find(EntityOrderItem::class, $loadedItem->getId());
        self::assertNotNull($entityItem);
        self::assertSame(2500, $entityItem->getUnitPrice());

        $loadedOrder->removeItem($loadedItem);
        $this->repository->save($loadedOrder);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(EntityOrderItem::class, $loadedItem->getId()));
        $savedOrder = $this->repository->find($loadedOrder->getId());
        self::assertNotNull($savedOrder);
        self::assertCount(0, $savedOrder->getItems());
    }

    #[Test]
    public function orphanRemovalDeletesAnItemWithoutNullingItsRequiredOrder(): void
    {
        $this->repository->save($this->order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $entityOrder = $this->entityManager->find(EntityOrder::class, $this->order->getId());
        self::assertNotNull($entityOrder);
        $entityItem = $entityOrder->getItems()->first();
        self::assertInstanceOf(EntityOrderItem::class, $entityItem);
        $entityOrder->removeItem($entityItem);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(EntityOrderItem::class, $this->item->getId()));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function itemPersistenceStates(): iterable
    {
        yield 'new item' => [false];
        yield 'existing item' => [true];
    }

    #[Test]
    #[DataProvider('itemPersistenceStates')]
    public function rejectsMissingProductsForBothNewAndExistingItems(bool $persisted): void
    {
        if ($persisted) {
            $this->repository->save($this->order);
            $this->entityManager->flush();
        }

        $missingProductId = Uuid::v7()->toRfc4122();
        $this->order->removeItem($this->item);
        $this->order->addItem(OrderItem::create($this->item->getId(), new ProductSnapshot($missingProductId, 'Missing', new Money(2500)), 2));

        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage($missingProductId);

        $this->repository->save($this->order);
    }
}
