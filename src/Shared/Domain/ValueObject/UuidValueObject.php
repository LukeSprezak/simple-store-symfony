<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

abstract class UuidValueObject
{
    protected string $id;

    final public function __construct(string $id)
    {
        if (!Uuid::isValid($id)) {
            throw new \InvalidArgumentException('Incorrect UUID format.');
        }

        $this->id = $id;
    }

    public static function generate(): static
    {
        return new static(Uuid::v7()->toRfc4122());
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function equals(self|UuidValueObjectInterface $other): bool
    {
        return $this->id === $other->id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
