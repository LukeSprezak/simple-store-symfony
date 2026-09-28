<?php

declare(strict_types=1);

namespace App\Product\Application\Query\GetProduct;

use App\Product\Application\ReadModel\ProductReader;
use App\Product\Application\ReadModel\ProductView;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetProductQueryHandler implements QueryHandler
{
    public function __construct(private ProductReader $productReader)
    {
    }

    public function __invoke(GetProductQuery $query): ProductView
    {
        return $this->productReader->findActive($query->productId)
            ?? throw new ProductNotFoundException($query->productId);
    }
}
