<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

final readonly class OrderPage
{
    /**
     * @param list<OrderView> $items
     */
    public function __construct(public array $items, public ?string $nextCursor)
    {
    }
}
