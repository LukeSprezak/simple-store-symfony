<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\ProductUnavailableException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\CartItem;
use App\Product\Domain\Model\Product;
use App\User\Domain\ValueObject\UserId;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowInterface;

#[CoversClass(Cart::class)]
class CartTest extends TestCase
{
    #[Test]
    public function shouldAddProductToEmptyCart(): void
    {
        // Given
        $cart = Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, UserId::generate());
        $product = $this->createProductMock('product-1', 50.0, 10, 'Product 1');

        $product->expects($this->once())
            ->method('decreaseStock')
            ->with(2);

        // When
        $cart->addProduct($product, 2);

        // Then
        self::assertCount(1, $cart->getItems(), 'Cart should contain exactly one item.');
        $cartItem = $cart->getItems()->first();
        self::assertInstanceOf(CartItem::class, $cartItem, 'Cart item should be an instance of CartItem.');
        self::assertSame(2, $cartItem->getQuantity(), 'Cart item quantity should be 2.');
    }

    #[Test]
    public function shouldAddProductToExistingCartItem(): void
    {
        // Given
        $cart = Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, UserId::generate());

        $product = $this->createProductMock('product-1', 50.0, 10, 'Product 1');

        $expectedCalls = [2, 3];
        $callIndex = 0;
        $product->expects($this->exactly(2))
            ->method('decreaseStock')
            ->willReturnCallback(function ($quantity) use (&$expectedCalls, &$callIndex) {
                self::assertSame($expectedCalls[$callIndex], $quantity, 'decreaseStock called with unexpected quantity.');
                ++$callIndex;
            });

        // When
        $cart->addProduct($product, 2);
        $cart->addProduct($product, 3);

        // Then
        self::assertCount(1, $cart->getItems(), 'Cart should contain exactly one item.');
        $cartItem = $cart->getItems()->first();
        self::assertSame(5, $cartItem->getQuantity(), 'Cart item quantity should be 5.');
    }

    #[Test]
    public function shouldAddProductWithInsufficientStockThrowsException(): void
    {
        // Given
        $cart = Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, UserId::generate());
        $product = $this->createProductMock('product-1', 30.0, 1, 'Product 1');

        $product->expects($this->never())
            ->method('decreaseStock');

        // Then
        $this->expectException(ProductUnavailableException::class);
        $this->expectExceptionMessage('Not enough stock for product: Product 1');

        // When
        $cart->addProduct($product, 2);
    }

    #[Test]
    public function shouldGetTotalAmount(): void
    {
        // Given
        $cart = Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, UserId::generate());
        $product1 = $this->createProductMock('product-1', 50.0, 10, 'Product 1');
        $product2 = $this->createProductMock('product-2', 25.0, 10, 'Product 2');

        $product1->expects($this->once())
            ->method('decreaseStock')
            ->with(2);
        $product2->expects($this->once())
            ->method('decreaseStock')
            ->with(4);

        $cart->addProduct($product1, 2);
        $cart->addProduct($product2, 4);

        // When
        $total = $cart->getTotalAmount();

        // Then
        self::assertSame(200.0, $total, 'Total amount should be 200.0.');
    }

    #[Test]
    public function shouldCartIsExpired(): void
    {
        // Given
        $createdAt = (new \DateTimeImmutable())->modify('-25 hours');
        $expiresAt = (new \DateTimeImmutable())->modify('-1 hour');

        $cart = Cart::fromPersistence(
            id: Uuid::v7()->toRfc4122(),
            status: StatusCart::ACTIVE,
            ownerId: UserId::generate(),
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            items: []
        );

        // When
        $isExpired = $cart->isExpired();

        // Then
        self::assertTrue($isExpired, 'Cart should be expired.');
    }

    #[Test]
    public function shouldExpireCart(): void
    {
        // Given
        $cart = Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, UserId::generate());

        // When
        $cart->expire();

        // Then
        self::assertSame(StatusCart::EXPIRED, $cart->getStatus(), 'Cart status should be EXPIRED.');
    }

    #[Test]
    public function applyTransitionSuccess(): void
    {
        $now = new \DateTimeImmutable();
        $cart = new Cart('cart123', StatusCart::ACTIVE, UserId::generate(), $now, $now);
        $cart->setStatus(StatusCart::ACTIVE);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects($this->once())
            ->method('can')
            ->with($cart, 'convert')
            ->willReturn(true);
        $workflow->expects($this->once())
            ->method('apply')
            ->with($cart, 'convert');
        $workflow->expects($this->once())
            ->method('getMarking')
            ->with($cart)
            ->willReturn(new Marking(['converted_to_order' => 1]));

        $cart->applyTransition('convert', $workflow);

        $this->assertEquals(StatusCart::CONVERTED_TO_ORDER, $cart->getStatus());
    }

    #[Test]
    public function applyTransitionNotAllowed(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $now = new \DateTimeImmutable();
        $cart = new Cart('cart123', StatusCart::ACTIVE, UserId::generate(), $now, $now);
        $cart->setStatus(StatusCart::ACTIVE);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects($this->once())
            ->method('can')
            ->with($cart, 'convert')
            ->willReturn(false);

        $cart->applyTransition('convert', $workflow);
    }

    #[Test]
    public function applyTransitionMultipleActivePlaces(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cart should have exactly one active place.');

        $now = new \DateTimeImmutable();
        $cart = new Cart('cart123', StatusCart::ACTIVE, UserId::generate(), $now, $now);
        $cart->setStatus(StatusCart::ACTIVE);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects($this->once())
            ->method('can')
            ->with($cart, 'convert')
            ->willReturn(true);
        $workflow->expects($this->once())
            ->method('apply')
            ->with($cart, 'convert');
        $workflow->expects($this->once())
            ->method('getMarking')
            ->with($cart)
            ->willReturn(new Marking(['converted_to_order' => 1, 'active' => 1]));

        $cart->applyTransition('convert', $workflow);
    }

    #[Test]
    public function applyTransitionInvalidPlace(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Invalid status value 'invalid_status'.");

        $now = new \DateTimeImmutable();
        $cart = new Cart('cart123', StatusCart::ACTIVE, UserId::generate(), $now, $now);
        $cart->setStatus(StatusCart::ACTIVE);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects($this->once())
            ->method('can')
            ->with($cart, 'convert')
            ->willReturn(true);
        $workflow->expects($this->once())
            ->method('apply')
            ->with($cart, 'convert');
        $workflow->expects($this->once())
            ->method('getMarking')
            ->with($cart)
            ->willReturn(new Marking(['invalid_status' => 1]));

        $cart->applyTransition('convert', $workflow);
    }

    private function createProductMock(string $productId, float $price, int $stockQuantity, string $name = 'Test Product'): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);
        $product->method('getPrice')->willReturn($price);
        $product->method('getStockQuantity')->willReturn($stockQuantity);
        $product->method('getName')->willReturn($name);

        return $product;
    }
}
