<?php

declare(strict_types=1);

namespace App\Order\Domain\Enum;

use Symfony\Component\Workflow\Transition;

enum StatusCartTransition: string
{
    case EXPIRE = 'expire';
    case ABANDON = 'abandon';
    case CONVERT = 'convert';

    public static function tryFromWorkflowTransition(Transition $transition): ?self
    {
        return self::tryFrom($transition->getName());
    }
}
