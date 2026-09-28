<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

use App\User\Domain\ValueObject\UserId;

interface OrderStatusHistoryReader
{
    /**
     * @param UserId|null $ownerId null skips the ownership check
     */
    public function findOwnedBy(string $orderId, ?UserId $ownerId, int $limit, ?string $after): ?OrderStatusHistoryPage;
}
