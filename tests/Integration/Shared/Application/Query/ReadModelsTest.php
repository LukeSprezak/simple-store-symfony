<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Application\Query;

use App\Order\Application\Query\GetCart\GetCartQuery;
use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\ProductSnapshot;
use App\Product\Application\Query\GetProduct\GetProductQuery;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Product\Domain\Model\Product;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ReadModelsTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private QueryBus $queryBus;
    private UserId $ownerId;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->queryBus = self::getContainer()->get(QueryBus::class);
        $this->entityManager->getConnection()->beginTransaction();
        $this->ownerId = UserId::generate();
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
    public function readsCartSnapshotsAndTotalsWithoutHydratingAggregates(): void
    {
        $cart = $this->cart();
        $product = $this->product();
        $cart->addProduct(new ProductSnapshot($product->getId(), 'Original name', new Money(1234)), 3);
        $secondId = Uuid::v7()->toRfc4122();
        $cart->addProduct(new ProductSnapshot($secondId, 'Second product', new Money(500)), 2);
        $removedId = Uuid::v7()->toRfc4122();
        $cart->addProduct(new ProductSnapshot($removedId, 'Removed product', new Money(9999)), 1);
        $cart->removeProduct($removedId);
        $this->entityManager->persist($cart);
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->entityManager->getConnection()->update('product', ['name' => 'New name', 'price' => 99999, 'status' => 'inactive'], ['id' => $product->getId()]);
        $this->entityManager->clear();

        $view = $this->queryBus->ask(new GetCartQuery($cart->getId(), $this->ownerId));

        self::assertSame($cart->getId(), $view->id);
        self::assertSame('active', $view->status);
        self::assertSame($cart->getCreatedAt()->format(DATE_ATOM), $view->createdAt);
        self::assertSame($cart->getExpiresAt()->format(DATE_ATOM), $view->expiresAt);
        self::assertSame(4702, $view->totalAmountInCents);
        self::assertCount(2, $view->items);
        $byProduct = array_column($view->items, null, 'productId');
        self::assertSame('Original name', $byProduct[$product->getId()]->productName);
        self::assertSame(1234, $byProduct[$product->getId()]->unitPriceInCents);
        self::assertSame(3702, $byProduct[$product->getId()]->totalAmountInCents);
        self::assertSame(3, $byProduct[$product->getId()]->quantity);
        self::assertArrayNotHasKey($removedId, $byProduct);
        self::assertSame(0, $this->entityManager->getUnitOfWork()->size());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function emptyCarts(): iterable
    {
        yield 'no items' => [false];
        yield 'all items deleted' => [true];
    }

    #[Test]
    #[DataProvider('emptyCarts')]
    public function readsAnEmptyCartAsZero(bool $withDeletedItem): void
    {
        $cart = $this->cart();
        if ($withDeletedItem) {
            $productId = Uuid::v7()->toRfc4122();
            $cart->addProduct(new ProductSnapshot($productId, 'Deleted', new Money(100)), 1);
            $cart->removeProduct($productId);
        }
        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        $view = $this->queryBus->ask(new GetCartQuery($cart->getId(), $this->ownerId));

        self::assertSame([], $view->items);
        self::assertSame(0, $view->totalAmountInCents);
    }

    #[Test]
    public function treatsForeignAndMissingCartsAsNotFound(): void
    {
        $cart = $this->cart();
        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        foreach ([$cart->getId(), Uuid::v7()->toRfc4122()] as $id) {
            try {
                $this->queryBus->ask(new GetCartQuery($id, UserId::generate()));
                self::fail('A foreign or missing cart must not be returned.');
            } catch (CartNotFoundException $exception) {
                self::assertSame(sprintf('Cart with ID "%s" does not exist.', $id), $exception->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{StatusCart}>
     */
    public static function cartStatuses(): iterable
    {
        foreach (StatusCart::cases() as $status) {
            yield $status->value => [$status];
        }
    }

    #[Test]
    #[DataProvider('cartStatuses')]
    public function keepsHistoricalCartsReadableWithoutChangingTheirStatus(StatusCart $status): void
    {
        $cart = $this->cart();
        $this->entityManager->persist($cart);
        $this->entityManager->flush();
        $this->entityManager->getConnection()->update('cart', ['status' => $status->value, 'expires_at' => '2000-01-01 00:00:00'], ['id' => $cart->getId()]);

        $view = $this->queryBus->ask(new GetCartQuery($cart->getId(), $this->ownerId));

        self::assertSame($status->value, $view->status);
        self::assertStringStartsWith('2000-01-01T00:00:00', $view->expiresAt);
        self::assertSame($status->value, $this->entityManager->getConnection()->fetchOne('SELECT status FROM cart WHERE id = ?', [$cart->getId()]));
    }

    #[Test]
    public function productQueriesNeitherFlushPendingChangesNorHydrateAggregates(): void
    {
        $product = $this->product();
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $product->decreaseStock(1);

        $view = $this->queryBus->ask(new GetProductQuery($product->getId()));

        self::assertSame(10, $view->stockQuantity);
        self::assertSame(1234, $view->priceInCents);
        self::assertSame('Current product', $view->name);
        self::assertSame('Description', $view->description);
        self::assertSame(10, $this->entityManager->getConnection()->fetchOne('SELECT stock_quantity FROM product WHERE id = ?', [$product->getId()]));
        self::assertTrue($this->entityManager->getConnection()->isTransactionActive());
        $this->entityManager->clear();

        self::assertEquals($view, $this->queryBus->ask(new GetProductQuery($product->getId())));
        self::assertSame(0, $this->entityManager->getUnitOfWork()->size());
    }

    #[Test]
    public function hidesInactiveAndMissingProducts(): void
    {
        $product = $this->product();
        $product->deactivate();
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        foreach ([$product->getId(), Uuid::v7()->toRfc4122()] as $id) {
            try {
                $this->queryBus->ask(new GetProductQuery($id));
                self::fail('An inactive or missing product must not be returned.');
            } catch (ProductNotFoundException $exception) {
                self::assertSame(sprintf('Product with ID "%s" does not exist.', $id), $exception->getMessage());
            }
        }
    }

    private function cart(): Cart
    {
        return Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, $this->ownerId);
    }

    private function product(): Product
    {
        return Product::create(Uuid::v7()->toRfc4122(), 'Current product', 'Description', new Money(1234), 10, $this->ownerId);
    }
}
