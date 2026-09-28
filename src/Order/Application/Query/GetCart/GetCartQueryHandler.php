<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetCart;

use App\Order\Application\ReadModel\CartReader;
use App\Order\Application\ReadModel\CartView;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetCartQueryHandler implements QueryHandler
{
    public function __construct(private CartReader $cartReader)
    {
    }

    public function __invoke(GetCartQuery $query): CartView
    {
        return $this->cartReader->findOwnedBy($query->cartId, $query->ownerId)
            ?? throw new CartNotFoundException($query->cartId);
    }
}
