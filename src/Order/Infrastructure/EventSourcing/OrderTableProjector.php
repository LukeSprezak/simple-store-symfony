<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\EventSourcing;

use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

// Keeps the `order` and `order_item` read tables in the transaction that appends to the event stream.
final readonly class OrderTableProjector implements MessageConsumer
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Message $message): void
    {
        $event = $message->payload();
        $recordedAt = $message->timeOfRecording()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        if ($event instanceof OrderPlaced) {
            $this->connection->insert('`order`', ['id' => $event->orderId, 'status' => $event->status, 'owner_id' => $event->ownerId, 'created_at' => $event->createdAt->format('Y-m-d H:i:s')]);
            foreach ($event->items as $item) {
                $this->connection->insert('order_item', ['id' => $item['id'], 'order_id' => $event->orderId, 'product_id' => $item['productId'], 'quantity' => $item['quantity'], 'unit_price' => $item['unitPrice']]);
            }
        } elseif ($event instanceof OrderStatusChanged) {
            $this->connection->update('`order`', ['status' => $event->toStatus, 'updated_at' => $recordedAt], ['id' => $event->orderId]);
        }
    }
}
