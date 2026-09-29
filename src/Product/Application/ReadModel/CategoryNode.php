<?php

declare(strict_types=1);

namespace App\Product\Application\ReadModel;

final readonly class CategoryNode
{
    /**
     * @param list<CategoryNode> $children
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $icon,
        public array $children,
    ) {
    }
}
