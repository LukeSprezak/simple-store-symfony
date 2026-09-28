<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\Application;

use App\Order\Application\Command\AddProductToCart\AddProductToCartCommand;
use App\Order\Application\Command\ConvertCartToOrder\ConvertCartToOrderCommand;
use App\Order\Application\Command\RemoveProductFromCart\RemoveProductFromCartCommand;
use App\Order\Application\Service\RemoveExpiredCart\RemoveExpiredCartService;
use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\CartNotActiveException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\CartItem;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Infrastructure\Doctrine\Entity\Order;
use App\Order\Infrastructure\Doctrine\Entity\OrderItem;
use App\Order\Infrastructure\Repository\CartRepository;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Product\Domain\Model\Product;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Bus\Messenger\SyncCommandBus;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class CartLifecycleTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private SyncCommandBus $commandBus;
    private CartRepository $cartRepository;
    private UserId $ownerId;
    private string $cartId;
    private Product $firstProduct;
    private Product $secondProduct;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->commandBus = self::getContainer()->get(SyncCommandBus::class);
        $this->cartRepository = self::getContainer()->get(CartRepository::class);
        $this->entityManager->getConnection()->beginTransaction();
        $this->ownerId = UserId::generate();
        $uniqueName = str_replace('-', '', $this->ownerId->getId());
        $owner = new User()
            ->setId($this->ownerId->getId())
            ->setUsername($uniqueName)
            ->setEmail(substr($uniqueName, 0, 20).'@test.local')
            ->setPassword('unused')
            ->setRepeatPassword('unused')
            ->setEnabled(true);
        $this->entityManager->persist($owner);
        $this->cartId = Uuid::v7()->toRfc4122();
        $this->firstProduct = Product::create(Uuid::v7()->toRfc4122(), 'First', 'First product', new Money(1000), 10, $this->ownerId);
        $this->secondProduct = Product::create(Uuid::v7()->toRfc4122(), 'Second', 'Second product', new Money(2500), 8, $this->ownerId);
        $this->entityManager->persist($this->firstProduct);
        $this->entityManager->persist($this->secondProduct);
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
        }

        parent::tearDown();
    }

    #[Test]
    public function conversionExcludesRemovedItemsAndCannotReleaseSoldStock(): void
    {
        $this->addProduct($this->firstProduct, 3, true);
        $this->addProduct($this->secondProduct, 2);
        $this->commandBus->dispatch(new RemoveProductFromCartCommand($this->cartId, $this->firstProduct->getId(), $this->ownerId));
        $cart = $this->reloadCart();
        self::assertCount(1, $cart->getActiveItems());
        self::assertSame(5000, $cart->getTotalAmount()->getAmount());
        self::assertSame(10, $this->stock($this->firstProduct));
        self::assertSame(6, $this->stock($this->secondProduct));

        $this->commandBus->dispatch(new ConvertCartToOrderCommand($this->cartId, $this->ownerId));
        $cart = $this->reloadCart();
        self::assertSame(StatusCart::CONVERTED_TO_ORDER, $cart->getStatus());
        $orders = $this->entityManager->getRepository(Order::class)->findBy(['ownerId' => $this->ownerId->getId()]);
        self::assertCount(1, $orders);
        self::assertNotSame($this->cartId, $orders[0]->getId());
        self::assertCount(1, $orders[0]->getItems());
        $orderItem = $orders[0]->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $orderItem);
        self::assertSame($this->secondProduct->getId(), $orderItem->getProduct()->getId());
        self::assertSame(2, $orderItem->getQuantity());
        self::assertSame(2500, $orderItem->getUnitPrice());

        try {
            $this->commandBus->dispatch(new RemoveProductFromCartCommand($this->cartId, $this->secondProduct->getId(), $this->ownerId));
            self::fail('Removing an item from a converted cart must fail.');
        } catch (CartNotActiveException) {
            $cart = $this->reloadCart();
            self::assertCount(1, $cart->getActiveItems());
            self::assertSame(6, $this->stock($this->secondProduct));
        }

        self::getContainer()->get(RemoveExpiredCartService::class)->expireCarts(new \DateTimeImmutable('+2 days'));
        $this->entityManager->flush();
        $cart = $this->reloadCart();
        self::assertSame(StatusCart::CONVERTED_TO_ORDER, $cart->getStatus());
        self::assertSame(6, $this->stock($this->secondProduct));
    }

    #[Test]
    public function expirationReleasesOnlyRemainingReservationsAndDoesSoOnce(): void
    {
        $this->addProduct($this->firstProduct, 3, true);
        $this->addProduct($this->secondProduct, 2);
        $this->commandBus->dispatch(new RemoveProductFromCartCommand($this->cartId, $this->firstProduct->getId(), $this->ownerId));
        $this->entityManager->getConnection()->update('cart', ['expires_at' => '2000-01-01 00:00:00'], ['id' => $this->cartId]);
        $cart = $this->reloadCart();
        self::assertTrue($cart->isExpired());
        self::assertSame(10, $this->stock($this->firstProduct));
        self::assertSame(6, $this->stock($this->secondProduct));
        $service = self::getContainer()->get(RemoveExpiredCartService::class);
        $now = new \DateTimeImmutable();

        $service->expireCarts($now);
        $this->entityManager->flush();
        $cart = $this->reloadCart();

        self::assertSame(StatusCart::EXPIRED, $cart->getStatus());
        foreach ($cart->getItems() as $item) {
            self::assertSame(0, $item->getQuantity());
        }
        self::assertSame(10, $this->stock($this->firstProduct));
        self::assertSame(8, $this->stock($this->secondProduct));

        $service->expireCarts($now);
        $this->entityManager->flush();
        $this->entityManager->clear();
        self::assertSame(10, $this->stock($this->firstProduct));
        self::assertSame(8, $this->stock($this->secondProduct));
    }

    #[Test]
    public function missingProductReachesTheCallerWithoutCreatingACart(): void
    {
        $this->expectException(ProductNotFoundException::class);

        try {
            $this->commandBus->dispatch(new AddProductToCartCommand($this->cartId, Uuid::v7()->toRfc4122(), 1, StatusCart::ACTIVE, $this->ownerId, true));
        } finally {
            $this->entityManager->clear();
            self::assertNull($this->cartRepository->find($this->cartId));
            self::assertSame(10, $this->stock($this->firstProduct));
        }
    }

    #[Test]
    public function roundTripPreservesDatesSnapshotsAndDeletionWithoutLockingTheCart(): void
    {
        $createdAt = new \DateTimeImmutable('-2 hours');
        $expiresAt = $createdAt->modify('+24 hours');
        $cart = new Cart($this->cartId, StatusCart::ACTIVE, $this->ownerId, $createdAt, $expiresAt);
        $cart->addProduct(new ProductSnapshot($this->firstProduct->getId(), 'First', new Money(1000)), 2);
        $cart->addProduct(new ProductSnapshot($this->secondProduct->getId(), 'Second', new Money(2500)), 1);
        $removedItem = $cart->getItems()->last();
        self::assertInstanceOf(CartItem::class, $removedItem);
        $cart->removeProduct($this->secondProduct->getId());
        $this->cartRepository->save($cart);
        $this->entityManager->flush();
        $this->entityManager->getConnection()->update('product', ['name' => 'Renamed', 'price' => 9999], ['id' => $this->firstProduct->getId()]);

        $cart = $this->reloadCart();

        self::assertSame(StatusCart::ACTIVE, $cart->getStatus());
        self::assertTrue($this->ownerId->equals($cart->getOwnerId()));
        self::assertSame($createdAt->format('Y-m-d H:i:s'), $cart->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame($expiresAt->format('Y-m-d H:i:s'), $cart->getExpiresAt()->format('Y-m-d H:i:s'));
        self::assertSame([], $cart->pullDomainEvents());
        self::assertCount(2, $cart->getItems());
        self::assertCount(1, $cart->getActiveItems());
        self::assertSame(2000, $cart->getTotalAmount()->getAmount());
        $activeItem = $cart->getActiveItems()->first();
        self::assertInstanceOf(CartItem::class, $activeItem);
        self::assertSame('First', $activeItem->getProduct()->getName());
        self::assertSame(1000, $activeItem->getProduct()->getPrice()->getAmount());
        $loadedRemovedItem = $cart->getItemById($removedItem->getId());
        self::assertNotNull($loadedRemovedItem);
        self::assertTrue($loadedRemovedItem->isDeleted());
        self::assertNotNull($loadedRemovedItem->getDeletedAt());

        // Re-adding a deleted product creates an active item without reviving the old one.
        $cart->addProduct(new ProductSnapshot($this->secondProduct->getId(), 'Second', new Money(3000)), 3);
        $this->cartRepository->save($cart);
        $this->entityManager->flush();
        $cart = $this->reloadCart();

        self::assertCount(3, $cart->getItems());
        self::assertCount(2, $cart->getActiveItems());
        self::assertSame(11000, $cart->getTotalAmount()->getAmount());
        self::assertSame($expiresAt->format('Y-m-d H:i:s'), $cart->getExpiresAt()->format('Y-m-d H:i:s'));
        $loadedRemovedItem = $cart->getItemById($removedItem->getId());
        self::assertNotNull($loadedRemovedItem);
        self::assertTrue($loadedRemovedItem->isDeleted());
    }

    private function addProduct(Product $product, int $quantity, bool $createCart = false): void
    {
        $this->commandBus->dispatch(new AddProductToCartCommand($this->cartId, $product->getId(), $quantity, StatusCart::ACTIVE, $this->ownerId, $createCart));
    }

    private function reloadCart(): Cart
    {
        $this->entityManager->clear();
        $cart = $this->cartRepository->find($this->cartId);
        self::assertNotNull($cart);

        return $cart;
    }

    private function stock(Product $product): int
    {
        $storedProduct = $this->entityManager->find(Product::class, $product->getId());
        self::assertNotNull($storedProduct);

        return $storedProduct->getStockQuantity();
    }
}
