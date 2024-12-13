<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Service\RemoveExpiredCart;

use App\Order\Application\Service\RemoveExpiredCart\RemoveExpiredCartService;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\CartItem;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Product\Domain\Model\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveExpiredCartService::class)]
final class RemoveExpiredCartServiceTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private ProductRepositoryInterface&MockObject $productRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private RemoveExpiredCartService $service;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new RemoveExpiredCartService(
            cartRepository: $this->cartRepository,
            productRepository: $this->productRepository,
            entityManager: $this->entityManager,
        );
    }

    #[Test]
    public function shouldCheckWhenThereAreNoCartToExpire(): void
    {
        // given
        $now = new \DateTimeImmutable();
        $this->cartRepository
            ->expects($this->once())
            ->method('findExpiredCarts')
            ->with($now)
            ->willReturn([]);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');
        // when
        $this->service->expireCarts($now);
    }

    #[Test]
    public function shouldWhenCartExpiresReturnStockQuantityProducts(): void
    {
        // given
        $now = new \DateTimeImmutable();

        $product1 = $this->createConfiguredMock(Product::class, [
            'getId' => 'prod-1',
            'getStockQuantity' => 10,
        ]);
        $product2 = $this->createConfiguredMock(Product::class, [
            'getId' => 'prod-2',
            'getStockQuantity' => 20,
        ]);

        $cartItem1 = $this->createConfiguredMock(CartItem::class, [
            'getProduct' => $product1,
            'getQuantity' => 2,
        ]);
        $cartItem2 = $this->createConfiguredMock(CartItem::class, [
            'getProduct' => $product2,
            'getQuantity' => 3,
        ]);

        $cart = $this->createMock(Cart::class);
        $cart->method('getItems')->willReturn(new ArrayCollection([$cartItem1, $cartItem2]));

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

        $this->productRepository
            ->expects($this->once())
            ->method('findByIds')
            ->with(['prod-1', 'prod-2'])
            ->willReturn([$product1, $product2]);

        $product1
            ->expects($this->once())
            ->method('increaseStock')
            ->with(2);

        $product2
            ->expects($this->once())
            ->method('increaseStock')
            ->with(3);

        $this->productRepository
            ->expects($this->once())
            ->method('findByIds')
            ->with(['prod-1', 'prod-2'])
            ->willReturn([$product1, $product2]);

        $savedProducts = [];
        $this->productRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function ($product) use ($product1, $product2) {
                return $product === $product1 || $product === $product2;
            }))
            ->willReturnCallback(function ($product) use (&$savedProducts) {
                $savedProducts[] = $product;
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // when
        $this->service->expireCarts($now);

        // then
        $this->assertContains($product1, $savedProducts);
        $this->assertContains($product2, $savedProducts);
    }

    #[Test]
    public function shouldExpireCartsWithExceptionDuringProcessing(): void
    {
        // given
        $now = new \DateTimeImmutable();
        $exception = new \Exception('Database error');

        $cart = $this->createMock(Cart::class);
        $cart->method('getItems')->willReturn(new ArrayCollection());
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
            ->with($cart);

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willThrowException($exception);

        // then
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        // when
        $this->service->expireCarts($now);
    }
}
