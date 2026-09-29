<?php

declare(strict_types=1);

namespace App\Product\Application\Query\GetCategories;

use App\Product\Application\ReadModel\CategoryReader;
use App\Product\Application\ReadModel\CategoryTree;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetCategoriesQueryHandler implements QueryHandler
{
    public function __construct(
        private CategoryReader $reader,
    ) {
    }

    public function __invoke(GetCategoriesQuery $query): CategoryTree
    {
        return $this->reader->findTree();
    }
}
