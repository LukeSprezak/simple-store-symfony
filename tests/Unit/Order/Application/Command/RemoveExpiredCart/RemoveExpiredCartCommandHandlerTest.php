<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command\RemoveExpiredCart;

use App\Order\Application\Command\RemoveExpiredCart\RemoveExpiredCartCommand;
use App\Order\Application\Command\RemoveExpiredCart\RemoveExpiredCartCommandHandler;
use App\Order\Application\Service\RemoveExpiredCart\RemoveExpiredCartService;
use App\Order\Domain\Exception\OrderCreateException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\ClockInterface;

#[CoversClass(RemoveExpiredCartCommandHandler::class)]
final class RemoveExpiredCartCommandHandlerTest extends TestCase
{
    private RemoveExpiredCartService&MockObject $expireCartsService;
    private ClockInterface&MockObject $clock;
    private RemoveExpiredCartCommandHandler $handler;

    protected function setUp(): void
    {
        $this->expireCartsService = $this->createMock(RemoveExpiredCartService::class);
        $this->clock = $this->createMock(ClockInterface::class);

        $this->handler = new RemoveExpiredCartCommandHandler(
            expireCartsService: $this->expireCartsService,
            clock: $this->clock,
        );
    }

    #[Test]
    public function shouldHandleWithNoExpiredCarts(): void
    {
        // Given
        $now = new \DateTimeImmutable();
        $this->clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->expireCartsService
            ->expects($this->once())
            ->method('expireCarts')
            ->with($now);

        // When
        $command = new RemoveExpiredCartCommand();
        $this->handler->__invoke($command);
    }

    #[Test]
    public function shouldHandleWithExpiredCarts(): void
    {
        // Given
        $now = new \DateTimeImmutable();
        $this->clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->expireCartsService
            ->expects($this->once())
            ->method('expireCarts')
            ->with($now);

        // When
        $command = new RemoveExpiredCartCommand();
        $this->handler->__invoke($command);
    }

    #[Test]
    public function shouldHandleWithExceptionDuringProcessing(): void
    {
        // given
        $now = new \DateTimeImmutable();
        $exception = new OrderCreateException('Unable to create order from cart.');

        $this->clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->expireCartsService
            ->expects($this->once())
            ->method('expireCarts')
            ->with($now)
            ->willThrowException($exception);

        // then
        $this->expectException(OrderCreateException::class);
        $this->expectExceptionMessage('Unable to create order from cart.');

        // when
        $command = new RemoveExpiredCartCommand();
        $this->handler->__invoke($command);
    }
}
