<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Shared\Domain\ValueObject\Money;
final readonly class ProductSnapshot
{
    public function __construct(
        private string $id,
        private string $name,
        private Money $price,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }
}
