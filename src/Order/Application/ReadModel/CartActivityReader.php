<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

use App\User\Domain\ValueObject\UserId;

interface CartActivityReader
{
    public function findOwnedBy(string $cartId, UserId $ownerId, int $limit, ?string $after): ?CartActivityPage;
}
