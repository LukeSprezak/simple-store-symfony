<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetOrderStatusHistory;

use App\Order\Application\ReadModel\OrderStatusHistoryPage;
use App\Order\Application\ReadModel\OrderStatusHistoryReader;
use App\Order\Domain\Exception\OrderNotFoundException;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetOrderStatusHistoryQueryHandler implements QueryHandler
{
    public function __construct(private OrderStatusHistoryReader $reader)
    {
    }

    public function __invoke(GetOrderStatusHistoryQuery $query): OrderStatusHistoryPage
    {
        return $this->reader->findOwnedBy($query->orderId, $query->ownerId, $query->limit, $query->after)
            ?? throw new OrderNotFoundException($query->orderId);
    }
}
