<?php

declare(strict_types=1);

namespace App\Product\Application\Query\GetProduct;

use App\Product\Application\ReadModel\ProductView;
use App\Shared\Application\Bus\Query\Query;

/**
 * @implements Query<ProductView>
 */
final readonly class GetProductQuery implements Query
{
    public function __construct(public string $productId)
    {
    }

    public function resultType(): string
    {
        return ProductView::class;
    }
}
