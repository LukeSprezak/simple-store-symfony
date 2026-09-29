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
        /** @var list<array{id: string, parent_id: ?string, name: string, slug: string, icon: ?string}> $rows */
        $rows = $this->connection->fetchAllAssociative('SELECT id, parent_id, name, slug, icon FROM category ORDER BY position, name');

        $children = [];
        foreach ($rows as $row) {
            if (null !== $row['parent_id']) {
                $children[$row['parent_id']][] = new CategoryNode($row['id'], $row['name'], $row['slug'], $row['icon'], []);
            }
        }

        $items = [];
        foreach ($rows as $row) {
            if (null === $row['parent_id']) {
                $items[] = new CategoryNode($row['id'], $row['name'], $row['slug'], $row['icon'], $children[$row['id']] ?? []);
            }
        }

        return new CategoryTree($items);
    }
}
