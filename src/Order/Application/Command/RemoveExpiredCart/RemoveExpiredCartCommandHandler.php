<?php

declare(strict_types=1);

namespace App\Order\Application\Command\RemoveExpiredCart;

use App\Order\Application\Service\RemoveExpiredCart\RemoveExpiredCartService;
use App\Shared\Application\Bus\Command\Sync\CommandHandler;
use Symfony\Component\Clock\ClockInterface;

final readonly class RemoveExpiredCartCommandHandler implements CommandHandler
{
    public function __construct(
        private RemoveExpiredCartService $expireCartsService,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RemoveExpiredCartCommand $command): void
    {
        $this->expireCartsService->expireCarts($this->clock->now());
    }
}
