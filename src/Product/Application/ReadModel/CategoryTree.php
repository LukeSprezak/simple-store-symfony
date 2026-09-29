<?php

declare(strict_types=1);

namespace App\Product\Application\ReadModel;

final readonly class CategoryTree
{
    /**
     * @param list<CategoryNode> $items
     */
    public function __construct(public array $items)
    {
    }
}
