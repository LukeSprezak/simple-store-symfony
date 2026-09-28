<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ReadModel;

use App\Order\Application\ReadModel\CartItemView;
use App\Order\Application\ReadModel\CartReader;
use App\Order\Application\ReadModel\CartView;
use App\User\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;

final readonly class DbalCartReader implements CartReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findOwnedBy(string $id, UserId $ownerId): ?CartView
    {
        // One statement gives a consistent view of the cart and its non-deleted items.
        /** @var list<array{id: string, status: string, created_at: string, expires_at: string, item_id: ?string, product_id: ?string, product_name: ?string, product_price: int|string|null, quantity: int|string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.status, c.created_at, c.expires_at, i.id AS item_id, i.product_id, i.product_name, i.product_price, i.quantity
             FROM cart c
             LEFT JOIN cart_item i ON i.cart_id = c.id AND i.deleted_at IS NULL
             WHERE c.id = :id AND c.owner_id = :owner
             ORDER BY i.id',
            ['id' => $id, 'owner' => $ownerId->getId()]
        );

        if ([] === $rows) {
            return null;
        }

        $items = [];
        $total = 0;
        foreach ($rows as $row) {
            if (null === $row['item_id']) {
                continue;
            }

            $price = (int) $row['product_price'];
            $quantity = (int) $row['quantity'];
            $subtotal = $price * $quantity;
            $items[] = new CartItemView($row['item_id'], (string) $row['product_id'], (string) $row['product_name'], $price, $quantity, $subtotal);
            $total += $subtotal;
        }

        $cart = $rows[0];

        return new CartView(
            $cart['id'],
            $cart['status'],
            new \DateTimeImmutable($cart['created_at'])->format(DATE_ATOM),
            new \DateTimeImmutable($cart['expires_at'])->format(DATE_ATOM),
            $items,
            $total
        );
    }
}
