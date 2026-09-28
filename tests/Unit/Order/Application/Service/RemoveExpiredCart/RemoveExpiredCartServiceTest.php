<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Service\RemoveExpiredCart;

use App\Order\Application\Service\RemoveExpiredCart\RemoveExpiredCartService;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\CartItem;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Service\StockReservation;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveExpiredCartService::class)]
final class RemoveExpiredCartServiceTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private StockReservation&MockObject $stockReservation;
    private RemoveExpiredCartService $service;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->stockReservation = $this->createMock(StockReservation::class);

        $this->service = new RemoveExpiredCartService(
            cartRepository: $this->cartRepository,
            stockReservation: $this->stockReservation,
        );
    }

    #[Test]
    public function shouldCheckWhenThereAreNoCartToExpire(): void
    {
        $this->stockReservation->expects($this->never())->method('release');

        // given
        $now = new \DateTimeImmutable();
        $this->cartRepository
            ->expects($this->once())
            ->method('findExpiredCarts')
            ->with($now)
            ->willReturn([]);

        $this->cartRepository
            ->expects($this->never())
            ->method('save');
        // when
        $this->service->expireCarts($now);
    }

    #[Test]
    public function shouldWhenCartExpiresReturnStockQuantityProducts(): void
    {
        // given
        $now = new \DateTimeImmutable();

        $product1 = new ProductSnapshot('prod-1', 'Product 1', new Money(1000));
        $product2 = new ProductSnapshot('prod-2', 'Product 2', new Money(2000));

        $cartItem1 = $this->createConfiguredStub(CartItem::class, [
            'getProduct' => $product1,
            'getQuantity' => 2,
        ]);
        $cartItem2 = $this->createConfiguredStub(CartItem::class, [
            'getProduct' => $product2,
            'getQuantity' => 3,
        ]);

        $cart = $this->createMock(Cart::class);
        $cart->method('getActiveItems')->willReturn(new ArrayCollection([$cartItem1, $cartItem2]));

        $this->cartRepository
            ->expects($this->once())
            ->method('findExpiredCarts')
            ->willReturn([$cart]);

        $cart
            ->expects($this->once())
            ->method('expire');

        $cart
            ->expects($this->once())
            ->method('clearItemQuantities');

        $this->cartRepository
            ->expects($this->once())
            ->method('save')
            ->with($cart);

        $released = [];
        $this->stockReservation
            ->expects($this->exactly(2))
            ->method('release')
            ->willReturnCallback(function (string $productId, int $quantity) use (&$released) {
                $released[$productId] = $quantity;
            });

        // when
        $this->service->expireCarts($now);

        // then
        $this->assertSame(['prod-1' => 2, 'prod-2' => 3], $released);
    }

    #[Test]
    public function shouldExpireCartsWithExceptionDuringProcessing(): void
    {
        $this->stockReservation->expects($this->never())->method('release');

        // given
        $now = new \DateTimeImmutable();
        $exception = new \Exception('Database error');

        $cart = $this->createMock(Cart::class);
        $cart->method('getActiveItems')->willReturn(new ArrayCollection());
        $cart->expects($this->once())->method('expire');
        $cart->expects($this->once())->method('clearItemQuantities');

        $this->cartRepository
            ->expects($this->once())
            ->method('findExpiredCarts')
            ->with($now)
            ->willReturn([$cart]);

        $this->cartRepository
            ->expects($this->once())
            ->method('save')
            ->with($cart)
            ->willThrowException($exception);

        // then
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        // when
        $this->service->expireCarts($now);
    }
}
