<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// Mapping for schema management; the projector and readers access this table through DBAL.
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'cart_activity')]
#[ORM\Index(name: 'idx_cart_activity_page', columns: ['cart_id', 'event_id'])]
class CartActivity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID)]
        private string $eventId,
        #[ORM\Column(type: Types::GUID)]
        private string $cartId,
        #[ORM\Column(length: 100)]
        private string $eventName,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $recordedAt,
        #[ORM\Column(type: Types::GUID, nullable: true)]
        private ?string $productId,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private ?int $quantity,
    ) {
    }
}
