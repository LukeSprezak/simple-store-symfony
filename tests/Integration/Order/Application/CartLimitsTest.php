<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\Application;

use App\Order\Application\Command\AddProductToCart\AddProductToCartCommand;
use App\Order\Application\Command\ConvertCartToOrder\ConvertCartToOrderCommand;
use App\Order\Application\Service\RemoveExpiredCart\RemoveExpiredCartService;
use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\ActiveCartLimitExceededException;
use App\Order\Domain\Exception\CartOwnerNotFoundException;
use App\Order\Domain\Exception\CartQuantityLimitExceededException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\CartItem;
use App\Order\Domain\Policy\CartLimits;
use App\Order\Infrastructure\Service\DoctrineCartCreationGuard;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Product\Domain\Model\Product;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Bus\Messenger\SyncCommandBus;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @phpstan-import-type Params from DriverManager
 */
final class CartLimitsTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private SyncCommandBus $commandBus;
    private UserId $ownerId;
    private Product $product;
    private bool $committedFixtures = false;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->commandBus = self::getContainer()->get(SyncCommandBus::class);
        $this->entityManager->getConnection()->beginTransaction();
        $this->ownerId = $this->createOwner();
        $this->product = Product::create(Uuid::v7()->toRfc4122(), 'Product', 'Description', new Money(1000), 1000, $this->ownerId);
        $this->entityManager->persist($this->product);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $connection = $this->entityManager->getConnection();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();

            // Only the concurrency test commits its uniquely identified fixtures.
            if ($this->committedFixtures) {
                $connection->executeStatement("DELETE o FROM domain_event_outbox o INNER JOIN cart c ON JSON_UNQUOTE(JSON_EXTRACT(o.payload, '$.cartId')) = c.id WHERE c.owner_id = :owner", ['owner' => $this->ownerId->getId()]);
                $connection->executeStatement("DELETE FROM domain_event_outbox WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.productId')) = :product", ['product' => $this->product->getId()]);
                $connection->executeStatement('DELETE ci FROM cart_item ci INNER JOIN cart c ON ci.cart_id = c.id WHERE c.owner_id = :owner', ['owner' => $this->ownerId->getId()]);
                $connection->delete('cart', ['owner_id' => $this->ownerId->getId()]);
                $connection->delete('product', ['id' => $this->product->getId()]);
                $connection->delete('user', ['id' => $this->ownerId->getId()]);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function enforcesTheCartLimitWithoutReservingMoreStockAndAllowsExistingCartUpdates(): void
    {
        $cartIds = $this->fillCartAllowance();
        $rejectedCartId = Uuid::v7()->toRfc4122();

        try {
            $this->addProduct($rejectedCartId, 1, true);
            self::fail('Creating another active cart must fail.');
        } catch (ActiveCartLimitExceededException) {
            $this->entityManager->clear();
            self::assertNull($this->entityManager->find(Cart::class, $rejectedCartId));
            self::assertSame(1000 - CartLimits::MAX_ACTIVE_CARTS_PER_OWNER, $this->stock());
        }

        $this->addProduct($cartIds[0], 1, false);
        $this->entityManager->clear();
        self::assertSame(2, $this->quantity($cartIds[0]));

        // Another account has its own allowance even when the first one is full.
        $otherOwner = $this->createOwner();
        $this->entityManager->flush();
        $otherCartId = Uuid::v7()->toRfc4122();
        $this->commandBus->dispatch(new AddProductToCartCommand($otherCartId, $this->product->getId(), 1, StatusCart::ACTIVE, $otherOwner, true));
        self::assertNotNull($this->entityManager->find(Cart::class, $otherCartId));
    }

    #[Test]
    public function conversionFreesAnAllowanceSlot(): void
    {
        $cartIds = $this->fillCartAllowance();
        $this->commandBus->dispatch(new ConvertCartToOrderCommand($cartIds[0], $this->ownerId));

        $newCartId = Uuid::v7()->toRfc4122();
        $this->addProduct($newCartId, 1, true);

        self::assertNotNull($this->entityManager->find(Cart::class, $newCartId));
    }

    #[Test]
    public function passedDeadlinesCountUntilTheSchedulerReleasesTheReservations(): void
    {
        $cartIds = $this->fillCartAllowance();
        $this->entityManager->getConnection()->update('cart', ['expires_at' => '2000-01-01 00:00:00'], ['id' => $cartIds[0]]);
        $this->entityManager->clear();
        $newCartId = Uuid::v7()->toRfc4122();

        try {
            $this->addProduct($newCartId, 1, true);
            self::fail('An unreleased reservation must still occupy a slot.');
        } catch (ActiveCartLimitExceededException) {
            self::assertSame(1000 - CartLimits::MAX_ACTIVE_CARTS_PER_OWNER, $this->stock());
        }

        self::getContainer()->get(RemoveExpiredCartService::class)->expireCarts(new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->addProduct($newCartId, 1, true);
        $this->entityManager->clear();

        self::assertSame(1000 - CartLimits::MAX_ACTIVE_CARTS_PER_OWNER, $this->stock());
        self::assertNotNull($this->entityManager->find(Cart::class, $newCartId));
    }

    #[Test]
    public function abandonedCartsWithReservationsStillOccupySlots(): void
    {
        $cartIds = $this->fillCartAllowance();
        $this->entityManager->getConnection()->update('cart', ['status' => StatusCart::ABANDONED->value], ['id' => $cartIds[0]]);
        $this->expectException(ActiveCartLimitExceededException::class);

        $this->addProduct(Uuid::v7()->toRfc4122(), 1, true);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function excessiveAdditions(): iterable
    {
        yield 'one beyond the accumulated limit' => [1];
        yield 'maximum integer' => [PHP_INT_MAX];
    }

    #[Test]
    #[DataProvider('excessiveAdditions')]
    public function rejectedQuantityDoesNotChangeItemsOrStock(int $additionalQuantity): void
    {
        $cartId = Uuid::v7()->toRfc4122();
        $this->addProduct($cartId, CartLimits::MAX_QUANTITY_PER_PRODUCT - 1, true);
        $this->addProduct($cartId, 1, false);
        $this->expectException(CartQuantityLimitExceededException::class);

        try {
            $this->addProduct($cartId, $additionalQuantity, false);
        } finally {
            $this->entityManager->clear();
            self::assertSame(CartLimits::MAX_QUANTITY_PER_PRODUCT, $this->quantity($cartId));
            self::assertSame(1000 - CartLimits::MAX_QUANTITY_PER_PRODUCT, $this->stock());
        }
    }

    #[Test]
    public function aFailedCreationDoesNotConsumeAnAllowanceSlot(): void
    {
        $failedCartId = Uuid::v7()->toRfc4122();
        try {
            $this->commandBus->dispatch(new AddProductToCartCommand($failedCartId, Uuid::v7()->toRfc4122(), 1, StatusCart::ACTIVE, $this->ownerId, true));
            self::fail('The missing product must be rejected.');
        } catch (ProductNotFoundException) {
            $this->entityManager->clear();
            self::assertNull($this->entityManager->find(Cart::class, $failedCartId));
        }

        self::assertCount(CartLimits::MAX_ACTIVE_CARTS_PER_OWNER, $this->fillCartAllowance());
    }

    #[Test]
    public function aMissingOwnerCannotBypassTheCreationLock(): void
    {
        $guard = new DoctrineCartCreationGuard($this->entityManager->getConnection());
        $this->expectException(CartOwnerNotFoundException::class);

        $guard->assertCanCreate(UserId::generate());
    }

    #[Test]
    public function concurrentCreationWaitsForTheOwnerAndSeesTheLatestCommittedCart(): void
    {
        for ($index = 0; $index < CartLimits::MAX_ACTIVE_CARTS_PER_OWNER - 1; ++$index) {
            $this->addProduct(Uuid::v7()->toRfc4122(), 1, true);
        }
        $connection = $this->entityManager->getConnection();
        $this->committedFixtures = true;
        $connection->commit();

        /** @var Params $connectionParameters */
        $connectionParameters = $connection->getParams();
        $competingConnection = DriverManager::getConnection($connectionParameters);
        $competingConnection->setTransactionIsolation(TransactionIsolationLevel::REPEATABLE_READ);
        $competingConnection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        $guard = new DoctrineCartCreationGuard($competingConnection);

        try {
            // Keep the first creation uncommitted after the command handler returns.
            $connection->beginTransaction();
            $this->addProduct(Uuid::v7()->toRfc4122(), 1, true);

            $competingConnection->beginTransaction();
            self::assertSame(CartLimits::MAX_ACTIVE_CARTS_PER_OWNER - 1, $this->countCarts($competingConnection));

            try {
                $guard->assertCanCreate($this->ownerId);
                self::fail('A competing request must wait until the first creation commits.');
            } catch (LockWaitTimeoutException) {
                self::assertTrue($connection->isTransactionActive());
            }

            $connection->commit();
            // A normal SELECT still sees the older snapshot; the guard must not use it.
            self::assertSame(CartLimits::MAX_ACTIVE_CARTS_PER_OWNER - 1, $this->countCarts($competingConnection));

            try {
                $guard->assertCanCreate($this->ownerId);
                self::fail('The newly committed cart must exhaust the remaining allowance.');
            } catch (ActiveCartLimitExceededException) {
                self::assertSame(CartLimits::MAX_ACTIVE_CARTS_PER_OWNER, $this->countCarts($connection));
                self::assertSame(1000 - CartLimits::MAX_ACTIVE_CARTS_PER_OWNER, $this->stock());
            }
        } finally {
            if ($competingConnection->isTransactionActive()) {
                $competingConnection->rollBack();
            }
            $competingConnection->close();
        }
    }

    #[Test]
    public function refusesToCheckTheAllowanceOutsideATransaction(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->rollBack();
        $this->expectException(\LogicException::class);

        new DoctrineCartCreationGuard($connection)->assertCanCreate($this->ownerId);
    }

    private function countCarts(Connection $connection): int
    {
        $count = $connection->fetchOne('SELECT COUNT(*) FROM cart WHERE owner_id = :owner', ['owner' => $this->ownerId->getId()]);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function createOwner(): UserId
    {
        $ownerId = UserId::generate();
        $uniqueName = str_replace('-', '', $ownerId->getId());
        $owner = new User()
            ->setId($ownerId->getId())
            ->setUsername($uniqueName)
            ->setEmail(substr($uniqueName, 0, 20).'@test.local')
            ->setPassword('unused')
            ->setEnabled(true);
        $this->entityManager->persist($owner);

        return $ownerId;
    }

    /**
     * @return non-empty-list<string>
     */
    private function fillCartAllowance(): array
    {
        $cartIds = [];
        for ($index = 0; $index < CartLimits::MAX_ACTIVE_CARTS_PER_OWNER; ++$index) {
            $cartId = Uuid::v7()->toRfc4122();
            $this->addProduct($cartId, 1, true);
            $cartIds[] = $cartId;
        }

        return $cartIds;
    }

    private function addProduct(string $cartId, int $quantity, bool $createCart): void
    {
        $this->commandBus->dispatch(new AddProductToCartCommand($cartId, $this->product->getId(), $quantity, StatusCart::ACTIVE, $this->ownerId, $createCart));
    }

    private function stock(): int
    {
        $product = $this->entityManager->find(Product::class, $this->product->getId());
        self::assertNotNull($product);

        return $product->getStockQuantity();
    }

    private function quantity(string $cartId): int
    {
        $cart = $this->entityManager->find(Cart::class, $cartId);
        self::assertNotNull($cart);
        $item = $cart->getActiveItems()->first();
        self::assertInstanceOf(CartItem::class, $item);

        return $item->getQuantity();
    }
}
