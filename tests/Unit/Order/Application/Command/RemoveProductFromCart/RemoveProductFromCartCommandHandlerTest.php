<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command\RemoveProductFromCart;

use App\Order\Application\Command\RemoveProductFromCart\RemoveProductFromCartCommand;
use App\Order\Application\Command\RemoveProductFromCart\RemoveProductFromCartCommandHandler;
use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\CartNotActiveException;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Order\Domain\Exception\ProductNotInCartException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Model\CartItem;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Service\StockReservation;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(RemoveProductFromCartCommandHandler::class)]
final class RemoveProductFromCartCommandHandlerTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private StockReservation&MockObject $stockReservation;
    private RemoveProductFromCartCommandHandler $handler;
    private Cart $cart;
    private CartItem $item;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->stockReservation = $this->createMock(StockReservation::class);
        $this->handler = new RemoveProductFromCartCommandHandler($this->cartRepository, $this->stockReservation);
        $this->cart = Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, UserId::generate());
        $this->cart->addProduct(new ProductSnapshot('product', 'Product', new Money(1000)), 3);
        $item = $this->cart->getItems()->first();
        self::assertInstanceOf(CartItem::class, $item);
        $this->item = $item;
        $this->cart->pullDomainEvents();
    }

    #[Test]
    public function removesTheItemAndReleasesExactlyItsReservedQuantity(): void
    {
        $this->cartRepository->expects($this->once())->method('find')->with($this->cart->getId())->willReturn($this->cart);
        $this->cartRepository->expects($this->once())->method('save')->with($this->cart);
        $this->stockReservation->expects($this->once())->method('release')->with('product', 3);

        ($this->handler)(new RemoveProductFromCartCommand($this->cart->getId(), 'product', $this->cart->getOwnerId()));

        self::assertTrue($this->item->isDeleted());
        self::assertTrue($this->cart->isEmpty());
    }

    #[Test]
    public function doesNotReleaseStockTwiceForTheSameItem(): void
    {
        $this->cart->removeProduct('product');
        $this->cartRepository->expects($this->once())->method('find')->willReturn($this->cart);
        $this->expectNoChanges();
        $this->expectException(ProductNotInCartException::class);

        ($this->handler)(new RemoveProductFromCartCommand($this->cart->getId(), 'product', $this->cart->getOwnerId()));
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
        $this->expectNoChanges();
        $this->expectException(CartNotFoundException::class);

        ($this->handler)(new RemoveProductFromCartCommand($this->cart->getId(), 'product', UserId::generate()));
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
    public function rejectsRemovalWithoutChangingTheItemOrReleasingStock(StatusCart $status, string $expiry): void
    {
        $cart = new Cart($this->cart->getId(), $status, $this->cart->getOwnerId(), new \DateTimeImmutable('-2 days'), new \DateTimeImmutable($expiry));
        $item = CartItem::create(Uuid::v7()->toRfc4122(), $cart, $this->item->getProduct(), 3);
        $cart->getItems()->add($item);
        $this->cartRepository->expects($this->once())->method('find')->willReturn($cart);
        $this->expectNoChanges();
        $this->expectException(CartNotActiveException::class);

        try {
            ($this->handler)(new RemoveProductFromCartCommand($cart->getId(), 'product', $cart->getOwnerId()));
        } finally {
            self::assertFalse($item->isDeleted());
            self::assertSame(3, $item->getQuantity());
            self::assertSame([], $cart->pullDomainEvents());
        }
    }

    private function expectNoChanges(): void
    {
        $this->cartRepository->expects($this->never())->method('save');
        $this->stockReservation->expects($this->never())->method('release');
    }
}
