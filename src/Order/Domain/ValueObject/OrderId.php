<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

use EventSauce\EventSourcing\AggregateRootId;

final readonly class OrderId implements AggregateRootId
{
    /**
     * @param non-empty-string $id
     */
    private function __construct(
        private string $id,
    ) {
    }

    public function toString(): string
    {
        return $this->id;
    }

    public static function fromString(string $aggregateRootId): static
    {
        return new static($aggregateRootId);
    }
}
