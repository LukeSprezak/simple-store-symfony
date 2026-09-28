<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command\AddProductToCart;

use App\Order\Application\Command\AddProductToCart\AddProductToCartCommand;
use App\Order\Application\Command\AddProductToCart\AddProductToCartCommandHandler;
use App\Order\Domain\Enum\StatusCart;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Order\Domain\Exception\ProductUnavailableException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Product\Domain\Model\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(AddProductToCartCommandHandler::class)]
class AddProductToCartCommandHandlerTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private ProductRepositoryInterface&MockObject $productRepository;
    private AddProductToCartCommandHandler $handler;
    private UserId $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = UserId::generate();

        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->handler = new AddProductToCartCommandHandler(
            $this->cartRepository,
            $this->productRepository
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

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with($productId)
            ->willReturn($product);

        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($existingCart);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($product);

        // When
        $this->handler->__invoke($command);

        // Then
        self::assertCount(1, $existingCart->getItems());
        self::assertSame(
            $quantity,
            $existingCart->getItemById($existingCart->getItems()->first()->getId())->getQuantity()
        );
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

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with($productId)
            ->willReturn($product);

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
                if ($item->getProduct()->getId() !== $productId) {
                    return false;
                }

                if ($item->getQuantity() !== $quantity) {
                    return false;
                }

                return true;
            }));

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($product);

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

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with($productId)
            ->willThrowException(new ProductNotFoundException($productId));

        $this->cartRepository->expects($this->never())
            ->method('save');

        $this->productRepository->expects($this->never())
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

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with($productId)
            ->willReturn($product);

        $this->cartRepository->expects($this->never())
            ->method('save');

        $this->productRepository->expects($this->never())
            ->method('save');

        // Then
        $this->expectException(ProductUnavailableException::class);
        $this->expectExceptionMessage('Not enough stock for product: Test Product');

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

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with($productId)
            ->willReturn($product);

        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Cart::class))
            ->willThrowException(new \Exception('Database connection error'));

        $this->productRepository->expects($this->never())
            ->method('save');

        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An unexpected error occurred while adding a product to the cart.');

        // When
        $this->handler->__invoke($command);
    }

    private function createProductMock(string $productId, int $stockQuantity, string $name = 'Test Product'): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);
        $product->method('getStockQuantity')->willReturn($stockQuantity);
        $product->method('getName')->willReturn($name);

        return $product;
    }
}
