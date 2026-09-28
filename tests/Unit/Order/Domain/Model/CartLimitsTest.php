<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\CartQuantityLimitExceededException;
use App\Order\Domain\Exception\InvalidQuantityException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\CartItem;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Domain\Policy\CartLimits;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CartLimitsTest extends TestCase
{
    private Cart $cart;
    private ProductSnapshot $product;

    protected function setUp(): void
    {
        $this->cart = Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, UserId::generate());
        $this->product = new ProductSnapshot('product', 'Product', new Money(1000));
    }

    #[Test]
    public function allowsTheLimitAcrossMultipleAdditionsAndAfterRemovingTheProduct(): void
    {
        $this->cart->addProduct($this->product, CartLimits::MAX_QUANTITY_PER_PRODUCT - 1);
        $this->cart->addProduct($this->product, 1);
        $item = $this->cart->getActiveItems()->first();
        self::assertInstanceOf(CartItem::class, $item);
        self::assertSame(CartLimits::MAX_QUANTITY_PER_PRODUCT, $item->getQuantity());

        $this->cart->removeProduct($this->product->getId());
        $this->cart->addProduct($this->product, CartLimits::MAX_QUANTITY_PER_PRODUCT);
        self::assertCount(1, $this->cart->getActiveItems());
        self::assertCount(2, $this->cart->getItems());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function excessiveQuantities(): iterable
    {
        yield 'new item' => [0, CartLimits::MAX_QUANTITY_PER_PRODUCT + 1];
        yield 'repeated addition' => [CartLimits::MAX_QUANTITY_PER_PRODUCT, 1];
        yield 'integer overflow' => [1, PHP_INT_MAX];
    }

    #[Test]
    #[DataProvider('excessiveQuantities')]
    public function rejectsExcessWithoutMutatingTheCart(int $initialQuantity, int $additionalQuantity): void
    {
        if ($initialQuantity > 0) {
            $this->cart->addProduct($this->product, $initialQuantity);
        }
        $this->cart->pullDomainEvents();
        $this->expectException(CartQuantityLimitExceededException::class);

        try {
            $this->cart->addProduct($this->product, $additionalQuantity);
        } finally {
            self::assertSame($initialQuantity * 1000, $this->cart->getTotalAmount()->getAmount());
            self::assertSame([], $this->cart->pullDomainEvents());
        }
    }

    #[Test]
    public function enforcesTheLimitWhenChangingAnItemDirectly(): void
    {
        $item = CartItem::create('item', $this->cart, $this->product, CartLimits::MAX_QUANTITY_PER_PRODUCT);
        $this->expectException(CartQuantityLimitExceededException::class);

        try {
            $item->increaseQuantity(PHP_INT_MAX);
        } finally {
            self::assertSame(CartLimits::MAX_QUANTITY_PER_PRODUCT, $item->getQuantity());
        }
    }

    #[Test]
    public function rejectsAnExcessiveItemAtCreation(): void
    {
        $this->expectException(CartQuantityLimitExceededException::class);

        CartItem::create('item', $this->cart, $this->product, CartLimits::MAX_QUANTITY_PER_PRODUCT + 1);
    }

    #[Test]
    public function rejectsAnExcessiveQuantitySetter(): void
    {
        $item = CartItem::create('item', $this->cart, $this->product, 1);
        $this->expectException(CartQuantityLimitExceededException::class);

        $item->setQuantity(CartLimits::MAX_QUANTITY_PER_PRODUCT + 1);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonPositiveQuantities(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[Test]
    #[DataProvider('nonPositiveQuantities')]
    public function rejectsNonPositiveAdditions(int $quantity): void
    {
        $this->expectException(InvalidQuantityException::class);

        $this->cart->addProduct($this->product, $quantity);
    }
}
