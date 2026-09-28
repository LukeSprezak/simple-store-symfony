<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'domain_event_outbox')]
#[ORM\Index(name: 'idx_outbox_pending', columns: ['published_at', 'available_at', 'id'])]
class OutboxMessage
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $attempts = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $availableAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID)]
        private string $id,
        #[ORM\Column(length: 100)]
        private string $eventName,
        #[ORM\Column(type: Types::TEXT)]
        private string $payload,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $recordedAt,
        #[ORM\Column(type: Types::INTEGER)]
        private int $schemaVersion = 1,
    ) {
        $this->availableAt = $recordedAt;
    }
}
