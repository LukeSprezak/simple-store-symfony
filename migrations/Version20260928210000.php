<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260928210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the order event stream and seed an order.placed event for every existing order';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE order_event (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, event_id CHAR(36) NOT NULL, aggregate_root_id CHAR(36) NOT NULL, version INT UNSIGNED NOT NULL, payload JSON NOT NULL, UNIQUE INDEX UNIQ_B8307E5A71F7E88B (event_id), UNIQUE INDEX uniq_order_event_stream_version (aggregate_root_id, version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        /** @var list<array{id: string, owner_id: string, status: string, created_at: string}> $orders */
        $orders = $this->connection->fetchAllAssociative('SELECT id, owner_id, status, created_at FROM `order` ORDER BY created_at, id');
        foreach ($orders as $order) {
            /** @var list<array{id: string, product_id: string, name: string, unit_price: int|string, quantity: int|string}> $items */
            $items = $this->connection->fetchAllAssociative(
                'SELECT i.id, i.product_id, p.name, i.unit_price, i.quantity FROM order_item i INNER JOIN product p ON p.id = i.product_id WHERE i.order_id = :order ORDER BY i.id',
                ['order' => $order['id']]
            );
            $eventId = Uuid::v4()->toRfc4122();
            // Matches EventSauce's ConstructingMessageSerializer output for App\Order\Domain\Event\OrderPlaced.
            $message = [
                'headers' => [
                    '__event_id' => $eventId,
                    '__time_of_recording' => new \DateTimeImmutable($order['created_at'], new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uO'),
                    '__time_of_recording_format' => 'Y-m-d H:i:s.uO',
                    '__aggregate_root_id' => $order['id'],
                    '__aggregate_root_type' => 'app.order.domain.model.order',
                    '__aggregate_root_version' => 1,
                    '__event_type' => 'app.order.domain.event.order_placed',
                    '__aggregate_root_id_type' => 'app.order.domain.value_object.order_id',
                ],
                'payload' => [
                    'order_id' => $order['id'],
                    'owner_id' => $order['owner_id'],
                    'status' => $order['status'],
                    'created_at' => new \DateTimeImmutable($order['created_at'], new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uO'),
                    'items' => array_map(static fn (array $item): array => [
                        'id' => $item['id'],
                        'productId' => $item['product_id'],
                        'productName' => $item['name'],
                        'unitPrice' => (int) $item['unit_price'],
                        'quantity' => (int) $item['quantity'],
                    ], $items),
                ],
            ];
            $this->addSql(
                'INSERT INTO order_event (event_id, aggregate_root_id, version, payload) VALUES (:event, :order, 1, :payload)',
                ['event' => $eventId, 'order' => $order['id'], 'payload' => json_encode($message, JSON_THROW_ON_ERROR)]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Dropping the event stream would discard the source of truth for orders.');
    }
}
