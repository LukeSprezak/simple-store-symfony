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

    public function findOwnedBy(string $orderId, ?UserId $ownerId, int $limit, ?string $after): ?OrderStatusHistoryPage
    {
        $ownerCondition = null === $ownerId ? '' : ' AND owner_id = :owner';
        $ownerParams = null === $ownerId ? [] : ['owner' => $ownerId->getId()];

        if (false === $this->connection->fetchOne('SELECT id FROM `order` WHERE id = :order'.$ownerCondition, ['order' => $orderId] + $ownerParams)) {
            return null;
        }

        /** @var list<array{event_id: string, transition: string, from_status: string, to_status: string, recorded_at: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT event_id, transition, from_status, to_status, recorded_at
             FROM order_status_history
             WHERE order_id = :order AND event_id > :after
             ORDER BY event_id LIMIT :limit',
            ['order' => $orderId, 'after' => $after ?? '', 'limit' => $limit + 1],
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
