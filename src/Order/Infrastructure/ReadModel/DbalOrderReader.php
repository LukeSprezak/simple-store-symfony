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

/**
 * @phpstan-type OrderRow array{id: string, status: string, customer_email: string, created_at: string, total: int|string}
 */
final readonly class DbalOrderReader implements OrderReader
{
    private const string SELECT = 'SELECT o.id, o.status, u.email AS customer_email, o.created_at, COALESCE(SUM(i.quantity * i.unit_price), 0) AS total
        FROM `order` o
        INNER JOIN user u ON u.id = o.owner_id
        LEFT JOIN order_item i ON i.order_id = o.id';
    private const string GROUP_BY = ' GROUP BY o.id, o.status, u.email, o.created_at';

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
        /** @var list<OrderRow> $rows */
        $rows = $this->connection->fetchAllAssociative(
            self::SELECT.([] === $conditions ? '' : ' WHERE '.implode(' AND ', $conditions)).self::GROUP_BY.' ORDER BY o.id DESC LIMIT :limit',
            $params,
            ['limit' => ParameterType::INTEGER]
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $items = array_map($this->toView(...), $rows);

        $lastItem = end($items);

        return new OrderPage($items, $hasMore && false !== $lastItem ? $lastItem->id : null);
    }

    public function find(string $orderId): ?OrderView
    {
        /** @var OrderRow|false $row */
        $row = $this->connection->fetchAssociative(self::SELECT.' WHERE o.id = :order'.self::GROUP_BY, ['order' => $orderId]);

        return false === $row ? null : $this->toView($row);
    }

    /**
     * @param OrderRow $row
     */
    private function toView(array $row): OrderView
    {
        return new OrderView(
            $row['id'],
            $row['status'],
            $row['customer_email'],
            new \DateTimeImmutable($row['created_at'])->format(DATE_ATOM),
            (int) $row['total'],
            array_values(array_map(
                static fn (StatusOrderTransition $transition): string => $transition->value,
                array_filter(StatusOrderTransition::cases(), static fn (StatusOrderTransition $transition): bool => in_array(StatusOrder::from($row['status']), $transition->allowedFrom(), true))
            )),
        );
    }
}
