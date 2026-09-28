<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Infrastructure\Doctrine\Entity\OutboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineOutboxEventBus implements EventBus
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ClockInterface $clock,
    ) {
    }

    public function publish(DomainEvent ...$domainEvents): void
    {
        foreach ($domainEvents as $event) {
            // Flush persists the aggregate and its outbox entries in the same transaction.
            $this->entityManager->persist(new OutboxMessage(
                Uuid::v7()->toRfc4122(),
                $event->eventName(),
                $this->serializer->serialize($event, 'json'),
                $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))
            ));
        }
    }
}
