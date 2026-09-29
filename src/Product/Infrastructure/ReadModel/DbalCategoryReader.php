<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\ReadModel;

use App\Product\Application\ReadModel\CategoryNode;
use App\Product\Application\ReadModel\CategoryReader;
use App\Product\Application\ReadModel\CategoryTree;
use Doctrine\DBAL\Connection;

final readonly class DbalCategoryReader implements CategoryReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findTree(): CategoryTree
    {
        // productCount counts products assigned directly to the category, not to its subcategories.
        /** @var list<array{id: string, parent_id: ?string, name: string, slug: string, icon: ?string, product_count: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.parent_id, c.name, c.slug, c.icon, COUNT(p.id) AS product_count
             FROM category c LEFT JOIN product p ON p.category_id = c.id
             GROUP BY c.id, c.parent_id, c.name, c.slug, c.icon, c.position
             ORDER BY c.position, c.name'
        );

        $children = [];
        foreach ($rows as $row) {
            if (null !== $row['parent_id']) {
                $children[$row['parent_id']][] = new CategoryNode($row['id'], $row['name'], $row['slug'], $row['icon'], (int) $row['product_count'], []);
            }
        }

        $items = [];
        foreach ($rows as $row) {
            if (null === $row['parent_id']) {
                $items[] = new CategoryNode($row['id'], $row['name'], $row['slug'], $row['icon'], (int) $row['product_count'], $children[$row['id']] ?? []);
            }
        }

        return new CategoryTree($items);
    }
}
