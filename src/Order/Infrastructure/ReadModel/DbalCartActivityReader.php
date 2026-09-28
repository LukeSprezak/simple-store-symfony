<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ReadModel;

use App\Order\Application\ReadModel\CartActivityItem;
use App\Order\Application\ReadModel\CartActivityPage;
use App\Order\Application\ReadModel\CartActivityReader;
use App\User\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalCartActivityReader implements CartActivityReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findOwnedBy(string $cartId, UserId $ownerId, int $limit, ?string $after): ?CartActivityPage
    {
        if (false === $this->connection->fetchOne('SELECT id FROM cart WHERE id = :cart AND owner_id = :owner', ['cart' => $cartId, 'owner' => $ownerId->getId()])) {
            return null;
        }

        /** @var list<array{event_id: string, event_name: string, recorded_at: string, product_id: ?string, quantity: int|string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.event_id, a.event_name, a.recorded_at, a.product_id, a.quantity
             FROM cart_activity a INNER JOIN cart c ON c.id = a.cart_id
             WHERE c.id = :cart AND c.owner_id = :owner AND a.event_id > :after
             ORDER BY a.event_id LIMIT :limit',
            ['cart' => $cartId, 'owner' => $ownerId->getId(), 'after' => $after ?? '', 'limit' => $limit + 1],
            ['limit' => ParameterType::INTEGER]
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $items = array_map(static fn (array $row): CartActivityItem => new CartActivityItem(
            $row['event_id'], $row['event_name'], new \DateTimeImmutable($row['recorded_at'], new \DateTimeZone('UTC'))->format(DATE_ATOM), $row['product_id'], null === $row['quantity'] ? null : (int) $row['quantity']
        ), $rows);

        $lastItem = end($items);

        return new CartActivityPage($items, $hasMore && false !== $lastItem ? $lastItem->eventId : null);
    }
}
