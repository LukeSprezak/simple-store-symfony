<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

use App\User\Domain\ValueObject\UserId;

interface OrderReader
{
    public function findOwnedPage(UserId $ownerId, int $limit, ?string $after): OrderPage;
}
