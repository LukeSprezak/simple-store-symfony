<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

interface UuidValueObjectInterface
{
    public function __toString(): string;

    public function equals(self $other): bool;

    public function getId(): string;
}
