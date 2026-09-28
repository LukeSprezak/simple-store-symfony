<?php

declare(strict_types=1);

namespace App\Product\Domain\Model;

use App\Product\Domain\Enum\StatusProduct;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;

class Product
{
    private int $version = 1;

    private function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly Money $price,
        private int $stockQuantity,
        private readonly UserId $userId,
        private StatusProduct $status = StatusProduct::ACTIVE,
    ) {
    }

    public static function create(
        string $id,
        string $name,
        string $description,
        Money $price,
        int $stockQuantity,
        UserId $userId,
    ): self {
        return new self($id, $name, $description, $price, $stockQuantity, $userId);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    public function increaseStock(int $quantity): void
    {
        $this->stockQuantity += $quantity;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getStatus(): StatusProduct
    {
        return $this->status;
    }

    public function deactivate(): void
    {
        $this->status = StatusProduct::INACTIVE;
    }

    public function decreaseStock(int $quantity): void
    {
        if ($quantity > $this->stockQuantity) {
            throw new \InvalidArgumentException('The number of products is insufficient: '.$this->name);
        }

        $this->stockQuantity -= $quantity;
    }
}
