<?php

declare(strict_types=1);

namespace App\Product\Application\Query\GetCategories;

use App\Product\Application\ReadModel\CategoryTree;
use App\Shared\Application\Bus\Query\Query;

/**
 * @implements Query<CategoryTree>
 */
final readonly class GetCategoriesQuery implements Query
{
    public function resultType(): string
    {
        return CategoryTree::class;
    }
}
