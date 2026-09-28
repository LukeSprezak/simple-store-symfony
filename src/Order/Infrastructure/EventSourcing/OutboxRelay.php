<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\EventSourcing;

use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Domain\Event\DomainEvent;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

final readonly class OutboxRelay implements MessageConsumer
{
    public function __construct(
        private EventBus $eventBus,
    ) {
    }

    public function handle(Message $message): void
    {
        /** @var DomainEvent $event */
        $event = $message->payload();
        $this->eventBus->publish($event);
    }
}
