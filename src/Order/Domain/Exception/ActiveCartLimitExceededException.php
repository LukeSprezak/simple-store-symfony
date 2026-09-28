<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Order\Domain\Policy\CartLimits;
use App\Shared\Domain\Exception\ConflictException;

final class ActiveCartLimitExceededException extends \RuntimeException implements ConflictException
{
    public function __construct()
    {
        parent::__construct(sprintf('A user may have at most %d active carts.', CartLimits::MAX_ACTIVE_CARTS_PER_OWNER));
    }
}
