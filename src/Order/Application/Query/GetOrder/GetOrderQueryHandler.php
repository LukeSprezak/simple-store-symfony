<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetOrder;

use App\Order\Application\ReadModel\OrderReader;
use App\Order\Application\ReadModel\OrderView;
use App\Order\Domain\Exception\OrderNotFoundException;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetOrderQueryHandler implements QueryHandler
{
    public function __construct(
        private OrderReader $reader,
    ) {
    }

    public function __invoke(GetOrderQuery $query): OrderView
    {
        return $this->reader->find($query->orderId)
            ?? throw new OrderNotFoundException($query->orderId);
    }
}
