<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetCartActivity;

use App\Order\Application\ReadModel\CartActivityPage;
use App\Shared\Application\Bus\Query\Query;
use App\User\Domain\ValueObject\UserId;

/**
 * @implements Query<CartActivityPage>
 */
final readonly class GetCartActivityQuery implements Query
{
    public function __construct(public string $cartId, public UserId $ownerId, public int $limit = 50, public ?string $after = null)
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Page size must be between 1 and 100.');
        }
    }

    public function resultType(): string
    {
        return CartActivityPage::class;
    }
}
