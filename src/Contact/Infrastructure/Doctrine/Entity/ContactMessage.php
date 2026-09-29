<?php

declare(strict_types=1);

namespace App\Contact\Infrastructure\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contact_message')]
class ContactMessage
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 100)]
        private string $name,
        #[ORM\Column(type: Types::STRING, length: 254)]
        private string $email,
        #[ORM\Column(type: Types::STRING, length: 150)]
        private string $subject,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $readAt = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function markRead(): void
    {
        $this->readAt ??= new \DateTimeImmutable();
    }

    public function markUnread(): void
    {
        $this->readAt = null;
    }
}
