<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Exception\InvalidQuantityException;
use App\Order\Domain\Model\CartItem;
use App\Product\Domain\Model\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(CartItem::class)]
class CartItemTest extends TestCase
{
    public function testCreateCartItemSuccessfully(): void
    {
        // Given
        $itemId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $quantity = 2;

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);

        // When
        $cartItem = CartItem::create($itemId, $product, $quantity);

        // Then
        self::assertSame($itemId, $cartItem->getId());
        self::assertSame($product, $cartItem->getProduct());
        self::assertSame($quantity, $cartItem->getQuantity());
    }

    #[Test]
    public function shouldCreateCartItemWithNegativeQuantityThrowsException(): void
    {
        // Given
        $itemId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $quantity = -1;

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);

        // Then
        $this->expectException(InvalidQuantityException::class);
        $this->expectExceptionMessage('The quantity cannot be negative.');

        // When
        CartItem::create($itemId, $product, $quantity);
    }

    #[Test]
    public function shouldIncreaseQuantitySuccessfully(): void
    {
        // Given
        $itemId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $initialQuantity = 1;
        $increaseAmount = 2;

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);

        $cartItem = CartItem::create($itemId, $product, $initialQuantity);

        // When
        $cartItem->increaseQuantity($increaseAmount);

        // Then
        self::assertSame(3, $cartItem->getQuantity());
    }

    #[Test]
    public function shouldIncreaseQuantityWithInvalidAmountThrowsException(): void
    {
        // Given
        $itemId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $initialQuantity = 1;
        $increaseAmount = 0;

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);

        $cartItem = CartItem::create($itemId, $product, $initialQuantity);

        // Then
        $this->expectException(InvalidQuantityException::class);
        $this->expectExceptionMessage('The quantity to be increased must be a positive number.');

        // When
        $cartItem->increaseQuantity($increaseAmount);
    }

    #[Test]
    public function shouldDecreaseQuantitySuccessfully(): void
    {
        // Given
        $itemId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $initialQuantity = 5;
        $decreaseAmount = 3;

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);

        $cartItem = CartItem::create($itemId, $product, $initialQuantity);

        // When
        $cartItem->decreaseQuantity($decreaseAmount);

        // Then
        self::assertSame(2, $cartItem->getQuantity());
    }

    #[Test]
    public function shouldDecreaseQuantityBelowZeroThrowsException(): void
    {
        // Given
        $itemId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $initialQuantity = 2;
        $decreaseAmount = 3;

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);

        $cartItem = CartItem::create($itemId, $product, $initialQuantity);

        // Then
        $this->expectException(InvalidQuantityException::class);
        $this->expectExceptionMessage('Cannot decrease quantity below zero.');

        // When
        $cartItem->decreaseQuantity($decreaseAmount);
    }

    #[Test]
    public function shouldSetQuantitySuccessfully(): void
    {
        // Given
        $itemId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $initialQuantity = 4;
        $newQuantity = 2;

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);

        $cartItem = CartItem::create($itemId, $product, $initialQuantity);

        // When
        $cartItem->setQuantity($newQuantity);

        // Then
        self::assertSame($newQuantity, $cartItem->getQuantity());
    }

    #[Test]
    public function shouldSetQuantityWithNegativeValueThrowsException(): void
    {
        // Given
        $itemId = Uuid::v7()->toRfc4122();
        $productId = Uuid::v7()->toRfc4122();
        $initialQuantity = 3;
        $newQuantity = -2;

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);

        $cartItem = CartItem::create($itemId, $product, $initialQuantity);

        // Then
        $this->expectException(InvalidQuantityException::class);
        $this->expectExceptionMessage('The quantity cannot be negative.');

        // When
        $cartItem->setQuantity($newQuantity);
    }
}
