<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command\RemoveExpiredCart;

use App\Order\Application\Command\RemoveExpiredCart\RemoveExpiredCartCommand;
use App\Order\Application\Command\RemoveExpiredCart\RemoveExpiredCartCommandHandler;
use App\Order\Application\Service\RemoveExpiredCart\RemoveExpiredCartService;
use App\Order\Domain\Exception\OrderCreateException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\ClockInterface;

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
    public function handleWithNoExpiredCarts(): void
    {
        $now = new \DateTimeImmutable();
        $this->clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->expireCartsService
            ->expects($this->once())
            ->method('expireCarts')
            ->with($now);

        $command = new RemoveExpiredCartCommand();
        $this->handler->__invoke($command);
    }

    #[Test]
    public function handleWithExpiredCarts(): void
    {
        $now = new \DateTimeImmutable();
        $this->clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->expireCartsService
            ->expects($this->once())
            ->method('expireCarts')
            ->with($now);

        $command = new RemoveExpiredCartCommand();
        $this->handler->__invoke($command);
    }

    #[Test]
    public function handleWithExceptionDuringProcessing(): void
    {
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

        $this->expectException(OrderCreateException::class);
        $this->expectExceptionMessage('Unable to create order from cart.');

        $command = new RemoveExpiredCartCommand();
        $this->handler->__invoke($command);
    }
}
