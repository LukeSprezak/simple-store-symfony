<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// Mapping for schema management; the projector and readers access this table through DBAL.
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'order_status_history')]
#[ORM\Index(name: 'idx_order_status_history_page', columns: ['order_id', 'event_id'])]
class OrderStatusHistory
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID)]
        private string $eventId,
        #[ORM\Column(type: Types::GUID)]
        private string $orderId,
        #[ORM\Column(length: 50)]
        private string $transition,
        #[ORM\Column(length: 50)]
        private string $fromStatus,
        #[ORM\Column(length: 50)]
        private string $toStatus,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $recordedAt,
    ) {
    }
}
