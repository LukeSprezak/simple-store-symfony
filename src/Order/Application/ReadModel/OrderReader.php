<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

use App\User\Domain\ValueObject\UserId;

interface OrderReader
{
    /**
     * @param UserId|null $ownerId null returns orders of all owners
     */
    public function findPage(?UserId $ownerId, int $limit, ?string $after): OrderPage;

    public function find(string $orderId): ?OrderView;
}
