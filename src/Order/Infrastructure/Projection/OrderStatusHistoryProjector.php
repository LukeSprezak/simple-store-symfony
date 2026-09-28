<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Projection;

use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Enum\StatusOrderTransition;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Shared\Application\Bus\Event\PublishedEvent;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'event.bus')]
final readonly class OrderStatusHistoryProjector
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(PublishedEvent $event): void
    {
        if (OrderStatusChanged::NAME !== $event->name) {
            return;
        }

        if (1 !== $event->schemaVersion || !Uuid::isValid($event->id)) {
            throw new UnrecoverableMessageHandlingException('Unsupported order event version or invalid event ID.');
        }

        try {
            $payload = json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR);
            $recordedAt = new \DateTimeImmutable($event->recordedAt)->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            throw new UnrecoverableMessageHandlingException('Invalid order event payload or date.', previous: $exception);
        }
        if (!is_array($payload) || !is_string($payload['orderId'] ?? null) || !Uuid::isValid($payload['orderId'])
            || !is_string($payload['transition'] ?? null) || null === StatusOrderTransition::tryFrom($payload['transition'])
            || !is_string($payload['fromStatus'] ?? null) || null === StatusOrder::tryFrom($payload['fromStatus'])
            || !is_string($payload['toStatus'] ?? null) || null === StatusOrder::tryFrom($payload['toStatus'])) {
            throw new UnrecoverableMessageHandlingException('An order status event requires a valid order ID, transition and statuses.');
        }

        // The event ID is the primary key. Redelivery and out-of-order delivery are harmless.
        $this->connection->executeStatement(
            'INSERT INTO order_status_history (event_id, order_id, transition, from_status, to_status, recorded_at)
             VALUES (:id, :order, :transition, :from, :to, :recorded)
             ON DUPLICATE KEY UPDATE event_id = event_id',
            ['id' => $event->id, 'order' => $payload['orderId'], 'transition' => $payload['transition'], 'from' => $payload['fromStatus'], 'to' => $payload['toStatus'], 'recorded' => $recordedAt->format('Y-m-d H:i:s')]
        );
    }
}
