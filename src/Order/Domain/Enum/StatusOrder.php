<?php

declare(strict_types=1);

namespace App\Order\Domain\Enum;

enum StatusOrder: string
{
    case CREATED = 'created';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case READY_FOR_SHIPMENT = 'ready_for_shipment';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case AWAITING_RECEIPT = 'awaiting_receipt';
    case RETRIEVED = 'retrieved';
    case CANCELLED = 'cancelled';
    case RETURN_REQUESTED = 'return_requested';
    case RETURNED = 'returned';
    case FAILED = 'failed';
}
