<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Domain\Exception\ConflictException;

final class OrderTransitionNotAllowedException extends \RuntimeException implements ConflictException
{
    public function __construct(string $transition, string $status)
    {
        parent::__construct(sprintf("Transition '%s' not allowed from status '%s'.", $transition, $status));
    }
}
