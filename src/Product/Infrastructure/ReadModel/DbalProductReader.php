<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\ReadModel;

use App\Product\Application\ReadModel\ProductReader;
use App\Product\Application\ReadModel\ProductView;
use App\Product\Domain\Enum\StatusProduct;
use Doctrine\DBAL\Connection;

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
}
