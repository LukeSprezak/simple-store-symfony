<?php

declare(strict_types=1);

namespace App\Order\Domain\Enum;

use Symfony\Component\Workflow\Transition;

enum StatusOrderTransition: string
{
    case PAY = 'pay';
    case CONFIRM_PAYMENT = 'confirm_payment';
    case PROCESS_ORDER = 'process_order';
    case MARK_READY_FOR_SHIPMENT = 'mark_ready_for_shipment';
    case SHIP = 'ship';
    case DELIVER = 'deliver';
    case CANCEL = 'cancel';
    case REQUEST_RETURN = 'request_return';
    case RETRIEVED = 'retrieved';
    case COMPLETE_RETURN = 'complete_return';
    case FAIL_PAYMENT = 'fail_payment';

    public static function tryFromWorkflowTransition(Transition $transition): ?self
    {
        return self::tryFrom($transition->getName());
    }
}
