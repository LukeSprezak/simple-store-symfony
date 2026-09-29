<?php

declare(strict_types=1);

namespace App\Product\Application\Query\GetProducts;

use App\Product\Application\ReadModel\ProductPage;
use App\Shared\Application\Bus\Query\Query;

/**
 * @implements Query<ProductPage>
 */
final readonly class GetProductsQuery implements Query
{
    public function __construct(public int $limit = 50, public ?string $after = null, public ?string $categorySlug = null)
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Page size must be between 1 and 100.');
        }
    }

    public function resultType(): string
    {
        return ProductPage::class;
    }
}
