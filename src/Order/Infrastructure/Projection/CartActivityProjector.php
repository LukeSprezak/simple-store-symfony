<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Projection;

use App\Order\Domain\Event\CartConverted;
use App\Order\Domain\Event\CartCreated;
use App\Order\Domain\Event\CartExpired;
use App\Order\Domain\Event\ProductAddedToCart;
use App\Order\Domain\Event\ProductRemovedFromCart;
use App\Shared\Application\Bus\Event\PublishedEvent;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'event.bus')]
final readonly class CartActivityProjector
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(PublishedEvent $event): void
    {
        if (!in_array($event->name, [CartCreated::NAME, CartConverted::NAME, CartExpired::NAME, ProductAddedToCart::NAME, ProductRemovedFromCart::NAME], true)) {
            return;
        }

        if (1 !== $event->schemaVersion || !Uuid::isValid($event->id)) {
            throw new UnrecoverableMessageHandlingException('Unsupported cart event version or invalid event ID.');
        }

        try {
            $payload = json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR);
            $recordedAt = new \DateTimeImmutable($event->recordedAt)->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            throw new UnrecoverableMessageHandlingException('Invalid cart event payload or date.', previous: $exception);
        }
        if (!is_array($payload) || !is_string($payload['cartId'] ?? null) || !Uuid::isValid($payload['cartId'])) {
            throw new UnrecoverableMessageHandlingException('A cart event requires a valid cart ID.');
        }
        $productId = $quantity = null;
        if (in_array($event->name, [ProductAddedToCart::NAME, ProductRemovedFromCart::NAME], true)) {
            $productId = $payload['productId'] ?? null;
            $quantity = $payload['quantity'] ?? null;
            if (!is_string($productId) || !Uuid::isValid($productId) || !is_int($quantity) || $quantity <= 0) {
                throw new UnrecoverableMessageHandlingException('Invalid product or quantity in cart event.');
            }
        }

        // The event ID is the primary key. Redelivery and out-of-order delivery are harmless.
        $this->connection->executeStatement(
            'INSERT INTO cart_activity (event_id, cart_id, event_name, recorded_at, product_id, quantity)
             VALUES (:id, :cart, :name, :recorded, :product, :quantity)
             ON DUPLICATE KEY UPDATE event_id = event_id',
            ['id' => $event->id, 'cart' => $payload['cartId'], 'name' => $event->name, 'recorded' => $recordedAt->format('Y-m-d H:i:s'), 'product' => $productId, 'quantity' => $quantity]
        );
    }
}
