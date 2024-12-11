<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command\RemoveExpiredCart;

use App\Order\Application\Command\RemoveExpiredCart\RemoveExpiredCartCommand;
use App\Order\Application\Command\RemoveExpiredCart\RemoveExpiredCartCommandHandler;
use App\Order\Application\Service\RemoveExpiredCart\RemoveExpiredCartService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class RemoveExpiredCartCommandHandlerTest extends TestCase
{
    private RemoveExpiredCartService|MockObject $service;
    private MockClock $clock;

    private RemoveExpiredCartCommandHandler $handler;

    protected function setUp(): void
    {
        $this->service = $this->createMock(RemoveExpiredCartService::class);
        $this->clock = new MockClock('2024-01-01 10:00:00');

        $this->handler = new RemoveExpiredCartCommandHandler(
            expireCartsService: $this->service,
            clock: $this->clock,
        );
    }

    public function testHandleCommand(): void
    {
        $command = new RemoveExpiredCartCommand();

        $expectedTime = $this->clock->now();

        $this->service->expects($this->once())
            ->method('expireCarts')
            ->with($this->equalTo($expectedTime));

        $this->handler->__invoke($command);
    }
}