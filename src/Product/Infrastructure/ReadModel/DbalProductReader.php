<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\ReadModel;

use App\Product\Application\ReadModel\ProductPage;
use App\Product\Application\ReadModel\ProductReader;
use App\Product\Application\ReadModel\ProductView;
use App\Product\Domain\Enum\StatusProduct;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalProductReader implements ProductReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findActive(string $id): ?ProductView
    {
        /** @var array{id: string, name: string, description: string, price: int|string, stock_quantity: int|string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, name, description, price, stock_quantity FROM product WHERE id = :id AND status = :status',
            ['id' => $id, 'status' => StatusProduct::ACTIVE->value]
        );

        return false === $row ? null : new ProductView($row['id'], $row['name'], $row['description'], (int) $row['price'], (int) $row['stock_quantity']);
    }

    public function findActivePage(int $limit, ?string $after): ProductPage
    {
        /** @var list<array{id: string, name: string, description: string, price: int|string, stock_quantity: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, description, price, stock_quantity FROM product
             WHERE status = :status AND id > :after
             ORDER BY id LIMIT :limit',
            ['status' => StatusProduct::ACTIVE->value, 'after' => $after ?? '', 'limit' => $limit + 1],
            ['limit' => ParameterType::INTEGER]
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $items = array_map(static fn (array $row): ProductView => new ProductView(
            $row['id'], $row['name'], $row['description'], (int) $row['price'], (int) $row['stock_quantity']
        ), $rows);

        $lastItem = end($items);

        return new ProductPage($items, $hasMore && false !== $lastItem ? $lastItem->id : null);
    }
}
