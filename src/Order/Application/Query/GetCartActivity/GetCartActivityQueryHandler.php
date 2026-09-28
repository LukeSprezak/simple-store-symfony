<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetCartActivity;

use App\Order\Application\ReadModel\CartActivityPage;
use App\Order\Application\ReadModel\CartActivityReader;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetCartActivityQueryHandler implements QueryHandler
{
    public function __construct(private CartActivityReader $reader)
    {
    }

    public function __invoke(GetCartActivityQuery $query): CartActivityPage
    {
        return $this->reader->findOwnedBy($query->cartId, $query->ownerId, $query->limit, $query->after)
            ?? throw new CartNotFoundException($query->cartId);
    }
}
