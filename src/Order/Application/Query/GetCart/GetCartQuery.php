<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetCart;

use App\Order\Application\ReadModel\CartView;
use App\Shared\Application\Bus\Query\Query;
use App\User\Domain\ValueObject\UserId;

/**
 * @implements Query<CartView>
 */
final readonly class GetCartQuery implements Query
{
    public function __construct(public string $cartId, public UserId $ownerId)
    {
    }

    public function resultType(): string
    {
        return CartView::class;
    }
}
