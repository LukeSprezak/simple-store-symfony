<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetOrder;

use App\Order\Application\ReadModel\OrderView;
use App\Shared\Application\Bus\Query\Query;

/**
 * @implements Query<OrderView>
 */
final readonly class GetOrderQuery implements Query
{
    public function __construct(public string $orderId)
    {
    }

    public function resultType(): string
    {
        return OrderView::class;
    }
}
