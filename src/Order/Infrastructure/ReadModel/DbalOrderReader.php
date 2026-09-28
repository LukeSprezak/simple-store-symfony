<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ReadModel;

use App\Order\Application\ReadModel\OrderPage;
use App\Order\Application\ReadModel\OrderReader;
use App\Order\Application\ReadModel\OrderView;
use App\User\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalOrderReader implements OrderReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findOwnedPage(UserId $ownerId, int $limit, ?string $after): OrderPage
    {
        // Newest first: order IDs are UUIDv7, so descending ID order is creation order.
        /** @var list<array{id: string, status: string, created_at: string, total: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.id, o.status, o.created_at, COALESCE(SUM(i.quantity * i.unit_price), 0) AS total
             FROM `order` o LEFT JOIN order_item i ON i.order_id = o.id
             WHERE o.owner_id = :owner'.(null === $after ? '' : ' AND o.id < :after').'
             GROUP BY o.id, o.status, o.created_at
             ORDER BY o.id DESC LIMIT :limit',
            ['owner' => $ownerId->getId(), 'limit' => $limit + 1] + (null === $after ? [] : ['after' => $after]),
            ['limit' => ParameterType::INTEGER]
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $items = array_map(static fn (array $row): OrderView => new OrderView(
            $row['id'], $row['status'], new \DateTimeImmutable($row['created_at'])->format(DATE_ATOM), (int) $row['total']
        ), $rows);

        $lastItem = end($items);

        return new OrderPage($items, $hasMore && false !== $lastItem ? $lastItem->id : null);
    }
}
