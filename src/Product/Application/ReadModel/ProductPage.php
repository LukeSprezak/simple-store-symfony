<?php

declare(strict_types=1);

namespace App\Product\Application\ReadModel;

final readonly class ProductPage
{
    /**
     * @param list<ProductView> $items
     */
    public function __construct(public array $items, public ?string $nextCursor)
    {
    }
}
