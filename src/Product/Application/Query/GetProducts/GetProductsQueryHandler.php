<?php

declare(strict_types=1);

namespace App\Product\Application\Query\GetProducts;

use App\Product\Application\ReadModel\ProductPage;
use App\Product\Application\ReadModel\ProductReader;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetProductsQueryHandler implements QueryHandler
{
    public function __construct(
        private ProductReader $productReader,
    ) {
    }

    public function __invoke(GetProductsQuery $query): ProductPage
    {
        return $this->productReader->findActivePage($query->limit, $query->after);
    }
}
