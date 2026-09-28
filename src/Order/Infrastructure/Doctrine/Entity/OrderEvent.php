<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// Mapping for schema management; the EventSauce message repository accesses this table through DBAL.
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'order_event')]
#[ORM\UniqueConstraint(name: 'uniq_order_event_stream_version', columns: ['aggregate_root_id', 'version'])]
class OrderEvent
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
        private int|string $id,
        #[ORM\Column(type: Types::GUID, unique: true)]
        private string $eventId,
        #[ORM\Column(type: Types::GUID)]
        private string $aggregateRootId,
        #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
        private int $version,
        #[ORM\Column(type: Types::JSON)]
        private string $payload,
    ) {
    }
}
