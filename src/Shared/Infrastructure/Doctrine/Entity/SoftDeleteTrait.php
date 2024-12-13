<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;

trait SoftDeleteTrait
{
    #[Column(type: Types::BOOLEAN)]
    private bool $deleted = false;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): void
    {
        $this->deleted = $deleted;
    }

    public function softDelete(): self
    {
        $this->deleted = true;
        $this->deletedAt = new \DateTimeImmutable();

        return $this;
    }

    public function restore(): self
    {
        $this->deleted = false;
        $this->deletedAt = null;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }
}
