<?php

declare(strict_types=1);

namespace App\Order\Application\ReadModel;

use App\User\Domain\ValueObject\UserId;

interface CartReader
{
    public function findOwnedBy(string $id, UserId $ownerId): ?CartView;
}
