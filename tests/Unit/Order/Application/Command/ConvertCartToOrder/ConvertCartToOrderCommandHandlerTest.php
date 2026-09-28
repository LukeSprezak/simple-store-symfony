<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command\ConvertCartToOrder;

use App\Order\Application\Command\ConvertCartToOrder\ConvertCartToOrderCommand;
use App\Order\Application\Command\ConvertCartToOrder\ConvertCartToOrderCommandHandler;
use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Exception\CartNotActiveException;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Order\Domain\Exception\CartTransitionNotAllowedException;
use App\Order\Domain\Exception\EmptyCartException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\CartItem;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderItem;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(ConvertCartToOrderCommandHandler::class)]
final class ConvertCartToOrderCommandHandlerTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private ConvertCartToOrderCommandHandler $handler;
    private Cart $cart;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->handler = new ConvertCartToOrderCommandHandler($this->cartRepository, $this->orderRepository);
        $this->cart = Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, UserId::generate());
    }

    #[Test]
    public function convertsOnlyActiveItemsAndPreservesOwnerAndPriceWithNewIds(): void
    {
        $snapshot = new ProductSnapshot('kept', 'Kept', new Money(1234));
        $this->cart->addProduct($snapshot, 2);
        $this->cart->addProduct(new ProductSnapshot('removed', 'Removed', new Money(9900)), 3);
        $this->cart->removeProduct('removed');
        $cartItem = $this->cart->getActiveItems()->first();
        self::assertInstanceOf(CartItem::class, $cartItem);
        $this->cartRepository->expects($this->once())->method('find')->with($this->cart->getId())->willReturn($this->cart);
        $this->cartRepository->expects($this->once())->method('save')->with($this->cart);
        $this->orderRepository->expects($this->once())->method('save')->with($this->callback(function (Order $order) use ($snapshot, $cartItem): bool {
            self::assertNotSame($this->cart->getId(), $order->getId());
            self::assertTrue(Uuid::isValid($order->getId()));
            self::assertTrue($this->cart->getOwnerId()->equals($order->getOwnerId()));
            self::assertSame(StatusOrder::CREATED->value, $order->getStatus());
            self::assertCount(1, $order->getItems());
            $item = $order->getItems()->first();
            self::assertInstanceOf(OrderItem::class, $item);
            self::assertNotSame($cartItem->getId(), $item->getId());
            self::assertSame($snapshot, $item->getProduct());
            self::assertSame(2, $item->getQuantity());

            return true;
        }));

        ($this->handler)(new ConvertCartToOrderCommand($this->cart->getId(), $this->cart->getOwnerId()));

        self::assertSame(StatusCart::CONVERTED_TO_ORDER, $this->cart->getStatus());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function emptyCarts(): iterable
    {
        yield 'no items' => [false];
        yield 'only deleted items' => [true];
    }

    #[Test]
    #[DataProvider('emptyCarts')]
    public function refusesEmptyCartsWithoutSavingAnOrder(bool $hasDeletedItem): void
    {
        if ($hasDeletedItem) {
            $this->cart->addProduct(new ProductSnapshot('removed', 'Removed', new Money(1000)), 1);
            $this->cart->removeProduct('removed');
        }

        $this->cartRepository->expects($this->once())->method('find')->willReturn($this->cart);
        $this->expectNoWrites();
        $this->expectException(EmptyCartException::class);

        try {
            ($this->handler)(new ConvertCartToOrderCommand($this->cart->getId(), $this->cart->getOwnerId()));
        } finally {
            self::assertSame(StatusCart::ACTIVE, $this->cart->getStatus());
        }
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
    public function hidesMissingAndForeignCarts(bool $exists): void
    {
        $this->cartRepository->expects($this->once())->method('find')->willReturn($exists ? $this->cart : null);
        $this->expectNoWrites();
        $this->expectException(CartNotFoundException::class);

        ($this->handler)(new ConvertCartToOrderCommand($this->cart->getId(), UserId::generate()));
    }

    #[Test]
    public function refusesAnotherConversionOfTheSameCart(): void
    {
        $this->cart->addProduct(new ProductSnapshot('product', 'Product', new Money(1000)), 1);
        $this->cart->convert();
        $this->cartRepository->expects($this->once())->method('find')->willReturn($this->cart);
        $this->expectNoWrites();
        $this->expectException(CartTransitionNotAllowedException::class);

        ($this->handler)(new ConvertCartToOrderCommand($this->cart->getId(), $this->cart->getOwnerId()));
    }

    /**
     * @return iterable<string, array{StatusCart}>
     */
    public static function convertibleStatuses(): iterable
    {
        yield 'active' => [StatusCart::ACTIVE];
        yield 'abandoned' => [StatusCart::ABANDONED];
    }

    #[Test]
    #[DataProvider('convertibleStatuses')]
    public function refusesConversionAfterTheReservationDeadline(StatusCart $status): void
    {
        $cart = new Cart($this->cart->getId(), $status, $this->cart->getOwnerId(), new \DateTimeImmutable('-2 days'), new \DateTimeImmutable('-1 day'));
        $cart->getItems()->add(CartItem::create(Uuid::v7()->toRfc4122(), $cart, new ProductSnapshot('product', 'Product', new Money(1000)), 1));
        $this->cartRepository->expects($this->once())->method('find')->willReturn($cart);
        $this->expectNoWrites();
        $this->expectException(CartNotActiveException::class);

        try {
            ($this->handler)(new ConvertCartToOrderCommand($cart->getId(), $cart->getOwnerId()));
        } finally {
            self::assertSame($status, $cart->getStatus());
            self::assertSame([], $cart->pullDomainEvents());
        }
    }

    private function expectNoWrites(): void
    {
        $this->cartRepository->expects($this->never())->method('save');
        $this->orderRepository->expects($this->never())->method('save');
    }
}
