<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

use App\User\Domain\ValueObject\UserId;

interface OrderStatusHistoryReader
{
    public function findOwnedBy(string $orderId, UserId $ownerId, int $limit, ?string $after): ?OrderStatusHistoryPage;
}
