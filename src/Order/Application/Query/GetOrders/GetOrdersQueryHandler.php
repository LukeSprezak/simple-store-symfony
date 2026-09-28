<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetOrders;

use App\Order\Application\ReadModel\OrderPage;
use App\Order\Application\ReadModel\OrderReader;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetOrdersQueryHandler implements QueryHandler
{
    public function __construct(
        private OrderReader $reader,
    ) {
    }

    public function __invoke(GetOrdersQuery $query): OrderPage
    {
        return $this->reader->findOwnedPage($query->ownerId, $query->limit, $query->after);
    }
}
