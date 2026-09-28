<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ReadModel;

use App\Order\Application\ReadModel\OrderStatusHistoryItem;
use App\Order\Application\ReadModel\OrderStatusHistoryPage;
use App\Order\Application\ReadModel\OrderStatusHistoryReader;
use App\User\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalOrderStatusHistoryReader implements OrderStatusHistoryReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findOwnedBy(string $orderId, UserId $ownerId, int $limit, ?string $after): ?OrderStatusHistoryPage
    {
        if (false === $this->connection->fetchOne('SELECT id FROM `order` WHERE id = :order AND owner_id = :owner', ['order' => $orderId, 'owner' => $ownerId->getId()])) {
            return null;
        }

        /** @var list<array{event_id: string, transition: string, from_status: string, to_status: string, recorded_at: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT h.event_id, h.transition, h.from_status, h.to_status, h.recorded_at
             FROM order_status_history h INNER JOIN `order` o ON o.id = h.order_id
             WHERE o.id = :order AND o.owner_id = :owner AND h.event_id > :after
             ORDER BY h.event_id LIMIT :limit',
            ['order' => $orderId, 'owner' => $ownerId->getId(), 'after' => $after ?? '', 'limit' => $limit + 1],
            ['limit' => ParameterType::INTEGER]
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $items = array_map(static fn (array $row): OrderStatusHistoryItem => new OrderStatusHistoryItem(
            $row['event_id'], $row['transition'], $row['from_status'], $row['to_status'], new \DateTimeImmutable($row['recorded_at'], new \DateTimeZone('UTC'))->format(DATE_ATOM)
        ), $rows);

        $lastItem = end($items);

        return new OrderStatusHistoryPage($items, $hasMore && false !== $lastItem ? $lastItem->eventId : null);
    }
}
