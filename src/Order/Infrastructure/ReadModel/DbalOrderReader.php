<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ReadModel;

use App\Order\Application\ReadModel\OrderPage;
use App\Order\Application\ReadModel\OrderReader;
use App\Order\Application\ReadModel\OrderView;
use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Enum\StatusOrderTransition;
use App\User\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalOrderReader implements OrderReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findPage(?UserId $ownerId, int $limit, ?string $after): OrderPage
    {
        $conditions = [];
        $params = ['limit' => $limit + 1];
        if (null !== $ownerId) {
            $conditions[] = 'o.owner_id = :owner';
            $params['owner'] = $ownerId->getId();
        }
        if (null !== $after) {
            $conditions[] = 'o.id < :after';
            $params['after'] = $after;
        }

        // Newest first: order IDs are UUIDv7, so descending ID order is creation order.
        /** @var list<array{id: string, status: string, created_at: string, total: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.id, o.status, o.created_at, COALESCE(SUM(i.quantity * i.unit_price), 0) AS total
             FROM `order` o LEFT JOIN order_item i ON i.order_id = o.id'.
            ([] === $conditions ? '' : ' WHERE '.implode(' AND ', $conditions)).'
             GROUP BY o.id, o.status, o.created_at
             ORDER BY o.id DESC LIMIT :limit',
            $params,
            ['limit' => ParameterType::INTEGER]
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $items = array_map(static fn (array $row): OrderView => new OrderView(
            $row['id'],
            $row['status'],
            new \DateTimeImmutable($row['created_at'])->format(DATE_ATOM),
            (int) $row['total'],
            array_values(array_map(
                static fn (StatusOrderTransition $transition): string => $transition->value,
                array_filter(StatusOrderTransition::cases(), static fn (StatusOrderTransition $transition): bool => in_array(StatusOrder::from($row['status']), $transition->allowedFrom(), true))
            )),
        ), $rows);

        $lastItem = end($items);

        return new OrderPage($items, $hasMore && false !== $lastItem ? $lastItem->id : null);
    }
}
