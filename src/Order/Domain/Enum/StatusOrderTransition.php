<?php

declare(strict_types=1);

namespace App\Order\Domain\Enum;

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

    /**
     * @return StatusOrder[]
     */
    public function allowedFrom(): array
    {
        return match ($this) {
            self::PAY => [StatusOrder::CREATED],
            self::CONFIRM_PAYMENT, self::FAIL_PAYMENT => [StatusOrder::PENDING_PAYMENT],
            self::PROCESS_ORDER => [StatusOrder::PAID],
            self::MARK_READY_FOR_SHIPMENT => [StatusOrder::PROCESSING],
            self::SHIP => [StatusOrder::READY_FOR_SHIPMENT],
            self::DELIVER => [StatusOrder::SHIPPED],
            self::CANCEL => [
                StatusOrder::CREATED,
                StatusOrder::PENDING_PAYMENT,
                StatusOrder::PAID,
                StatusOrder::PROCESSING,
                StatusOrder::READY_FOR_SHIPMENT,
            ],
            self::REQUEST_RETURN => [StatusOrder::DELIVERED],
            self::RETRIEVED => [StatusOrder::RETURN_REQUESTED],
            self::COMPLETE_RETURN => [StatusOrder::AWAITING_RECEIPT],
        };
    }

    public function target(): StatusOrder
    {
        return match ($this) {
            self::PAY => StatusOrder::PENDING_PAYMENT,
            self::CONFIRM_PAYMENT => StatusOrder::PAID,
            self::FAIL_PAYMENT => StatusOrder::FAILED,
            self::PROCESS_ORDER => StatusOrder::PROCESSING,
            self::MARK_READY_FOR_SHIPMENT => StatusOrder::READY_FOR_SHIPMENT,
            self::SHIP => StatusOrder::SHIPPED,
            self::DELIVER => StatusOrder::DELIVERED,
            self::CANCEL => StatusOrder::CANCELLED,
            self::REQUEST_RETURN => StatusOrder::RETURN_REQUESTED,
            self::RETRIEVED => StatusOrder::AWAITING_RECEIPT,
            self::COMPLETE_RETURN => StatusOrder::RETURNED,
        };
    }
}
