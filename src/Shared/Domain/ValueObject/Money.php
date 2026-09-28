<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class Money
{
    public function __construct(
        private int $amount,
    ) {
    }

    public static function fromDecimal(float $value): self
    {
        return new self((int) round($value * 100));
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor);
    }
}
