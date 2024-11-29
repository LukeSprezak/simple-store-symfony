<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\Command\Async\Command;
use App\Shared\Application\Bus\Command\Async\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AsyncCommandBus implements CommandBus
{
    public function __construct(
        private MessageBusInterface $commandAsyncBus,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function dispatch(Command $command): void
    {
        try {
            $this->commandAsyncBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }
    }
}
