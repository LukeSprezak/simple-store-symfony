<?php

declare(strict_types=1);

namespace App\Order\Domain\Service;

use App\User\Domain\ValueObject\UserId;

interface CartCreationGuard
{
    /**
     * Serialize creation for this owner until the current transaction commits or rolls back.
     * The caller must persist the new cart within that same transaction.
     */
    public function assertCanCreate(UserId $ownerId): void;
}
