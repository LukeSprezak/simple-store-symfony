<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command\AddProductToCart;

use App\Order\Application\Command\AddProductToCart\AddProductToCartCommand;
use App\Order\Application\Command\AddProductToCart\AddProductToCartCommandHandler;
use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\CartNotActiveException;
use App\Order\Domain\Exception\ActiveCartLimitExceededException;
use App\Order\Domain\Exception\CartQuantityLimitExceededException;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Order\Domain\Exception\ProductUnavailableException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\CartItem;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Domain\Policy\CartLimits;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Service\StockReservation;
use App\Order\Domain\Service\CartCreationGuard;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(AddProductToCartCommandHandler::class)]
class AddProductToCartCommandHandlerTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private StockReservation&MockObject $stockReservation;
    private AddProductToCartCommandHandler $handler;
    private UserId $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = UserId::generate();

        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->stockReservation = $this->createMock(StockReservation::class);
        $this->handler = new AddProductToCartCommandHandler(
            $this->cartRepository,
            $this->stockReservation,
            $this->createStub(CartCreationGuard::class),
        );
    }

    #[Test]
    public function shouldAddsProductToExistingCartSuccessfully(): void
    {
        // Given
        $cartId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $quantity = 2;

        $command = new AddProductToCartCommand($cartId, $productId, $quantity, StatusCart::ACTIVE, $this->userId, false);

        $existingCart = Cart::create($cartId, StatusCart::ACTIVE, $this->userId);
        $product = $this->createProductMock($productId, 10);
        $this->cartRepository->expects($this->once())
            ->method('find')
            ->with($cartId)
            ->willReturn($existingCart);

        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($existingCart);

        $this->stockReservation->expects($this->once())
            ->method('reserve')
            ->with($productId, $quantity)
            ->willReturn($product);

        // When
        $this->handler->__invoke($command);

        // Then
        self::assertCount(1, $existingCart->getItems());
        $item = $existingCart->getItems()->first();
        self::assertInstanceOf(CartItem::class, $item);
        self::assertSame($item, $existingCart->getItemById($item->getId()));
        self::assertSame($quantity, $item->getQuantity());
    }

    #[Test]
    public function shouldCreatesNewCartAndAddsProductSuccessfully(): void
    {
        // Given
        $cartId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $quantity = 1;

        $command = new AddProductToCartCommand($cartId, $productId, $quantity, StatusCart::ACTIVE, $this->userId, true);

        $product = $this->createProductMock($productId, 10);

        $this->cartRepository->expects($this->never())
            ->method('find');

        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Cart $cart) use ($cartId, $productId, $quantity) {
                if ($cart->getId() !== $cartId) {
                    return false;
                }

                if (StatusCart::ACTIVE !== $cart->getStatus()) {
                    return false;
                }

                if (1 !== $cart->getItems()->count()) {
                    return false;
                }

                $item = $cart->getItems()->first();
                self::assertInstanceOf(CartItem::class, $item);
                if ($item->getProduct()->getId() !== $productId) {
                    return false;
                }

                if ($item->getQuantity() !== $quantity) {
                    return false;
                }

                return true;
            }));

        $this->stockReservation->expects($this->once())
            ->method('reserve')
            ->with($productId, $quantity)
            ->willReturn($product);

        // When
        $this->handler->__invoke($command);
    }

    #[Test]
    public function shouldThrowsProductNotFoundExceptionWhenProductDoesNotExist(): void
    {
        // Given
        $cartId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $quantity = 1;

        $command = new AddProductToCartCommand($cartId, $productId, $quantity, StatusCart::ACTIVE, $this->userId, false);

        $existingCart = Cart::create($cartId, StatusCart::ACTIVE, $this->userId);
        $this->cartRepository->expects($this->once())
            ->method('find')
            ->with($cartId)
            ->willReturn($existingCart);

        $this->stockReservation->expects($this->once())
            ->method('reserve')
            ->with($productId, $quantity)
            ->willThrowException(new ProductNotFoundException($productId));

        $this->cartRepository->expects($this->never())
            ->method('save');

        // Then
        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Product with ID "%s" does not exist.', $productId));

        // When
        $this->handler->__invoke($command);
    }

    #[Test]
    public function shouldThrowsProductUnavailableExceptionWhenInsufficientStock(): void
    {
        // Given
        $cartId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $quantity = 10;

        $command = new AddProductToCartCommand($cartId, $productId, $quantity, StatusCart::ACTIVE, $this->userId, false);

        $existingCart = Cart::create($cartId, StatusCart::ACTIVE, $this->userId);
        $product = $this->createProductMock($productId, 5, 'Test Product');
        $this->cartRepository->expects($this->once())
            ->method('find')
            ->with($cartId)
            ->willReturn($existingCart);

        $this->cartRepository->expects($this->never())
            ->method('save');

        $this->stockReservation->expects($this->once())
            ->method('reserve')
            ->with($productId, $quantity)
            ->willThrowException(new ProductUnavailableException($productId));

        // Then
        $this->expectException(ProductUnavailableException::class);
        $this->expectExceptionMessage(sprintf('The product with the ID "%s" is not available.', $productId));

        // When
        $this->handler->__invoke($command);
    }

    #[Test]
    public function shouldThrowsRuntimeExceptionOnUnexpectedError(): void
    {
        // Given
        $cartId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $quantity = 2;

        $command = new AddProductToCartCommand($cartId, $productId, $quantity, StatusCart::ACTIVE, $this->userId, true);

        $product = $this->createProductMock($productId, 5);

        $this->cartRepository->expects($this->never())
            ->method('find');

        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Cart::class))
            ->willThrowException(new \Exception('Database connection error'));

        $this->stockReservation->expects($this->once())
            ->method('reserve')
            ->with($productId, $quantity)
            ->willReturn($product);

        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An unexpected error occurred while adding a product to the cart.');

        // When
        $this->handler->__invoke($command);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function inaccessibleCarts(): iterable
    {
        yield 'missing cart' => [false];
        yield 'another owner' => [true];
    }

    #[Test]
    #[DataProvider('inaccessibleCarts')]
    public function shouldRejectMissingAndForeignCartsBeforeReservingStock(bool $exists): void
    {
        $cartId = Uuid::v7()->toRfc4122();
        $cart = $exists ? Cart::create($cartId, StatusCart::ACTIVE, UserId::generate()) : null;
        $this->cartRepository->expects($this->once())->method('find')->with($cartId)->willReturn($cart);
        $this->cartRepository->expects($this->never())->method('save');
        $this->stockReservation->expects($this->never())->method('reserve');
        $this->expectException(CartNotFoundException::class);

        ($this->handler)(new AddProductToCartCommand($cartId, 'product', 1, StatusCart::ACTIVE, $this->userId, false));
    }

    /**
     * @return iterable<string, array{StatusCart, string}>
     */
    public static function unmodifiableCarts(): iterable
    {
        yield 'converted' => [StatusCart::CONVERTED_TO_ORDER, '+1 day'];
        yield 'expired' => [StatusCart::EXPIRED, '+1 day'];
        yield 'abandoned' => [StatusCart::ABANDONED, '+1 day'];
        yield 'deadline passed before scheduler runs' => [StatusCart::ACTIVE, '-1 day'];
    }

    #[Test]
    #[DataProvider('unmodifiableCarts')]
    public function shouldRejectInactiveCartsBeforeReservingStock(StatusCart $status, string $expiry): void
    {
        $cart = new Cart(Uuid::v7()->toRfc4122(), $status, $this->userId, new \DateTimeImmutable('-2 days'), new \DateTimeImmutable($expiry));
        $this->cartRepository->expects($this->once())->method('find')->willReturn($cart);
        $this->cartRepository->expects($this->never())->method('save');
        $this->stockReservation->expects($this->never())->method('reserve');
        $this->expectException(CartNotActiveException::class);

        ($this->handler)(new AddProductToCartCommand($cart->getId(), 'product', 1, StatusCart::ACTIVE, $this->userId, false));
    }

    #[Test]
    public function shouldRejectExcessQuantityBeforeReservingStock(): void
    {
        $cart = Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, $this->userId);
        $cart->addProduct(new ProductSnapshot('product', 'Product', new Money(1000)), CartLimits::MAX_QUANTITY_PER_PRODUCT);
        $this->cartRepository->expects($this->once())->method('find')->willReturn($cart);
        $this->cartRepository->expects($this->never())->method('save');
        $this->stockReservation->expects($this->never())->method('reserve');
        $this->expectException(CartQuantityLimitExceededException::class);

        ($this->handler)(new AddProductToCartCommand($cart->getId(), 'product', 1, StatusCart::ACTIVE, $this->userId, false));
    }

    #[Test]
    public function shouldCheckTheCartLimitBeforeReservingStock(): void
    {
        $guard = $this->createMock(CartCreationGuard::class);
        $guard->expects($this->once())->method('assertCanCreate')->with($this->userId)->willThrowException(new ActiveCartLimitExceededException());
        $handler = new AddProductToCartCommandHandler($this->cartRepository, $this->stockReservation, $guard);
        $this->cartRepository->expects($this->never())->method('save');
        $this->stockReservation->expects($this->never())->method('reserve');
        $this->expectException(ActiveCartLimitExceededException::class);

        $handler(new AddProductToCartCommand(Uuid::v7()->toRfc4122(), 'product', 1, StatusCart::ACTIVE, $this->userId, true));
    }

    private function createProductMock(string $productId, int $stockQuantity, string $name = 'Test Product'): ProductSnapshot
    {
        return new ProductSnapshot($productId, $name, new Money(1000));
    }
}
