<?php

declare(strict_types=1);

namespace App\Product\Domain\Model;

use App\Product\Domain\Enum\StatusProduct;
use App\User\Domain\ValueObject\UserId;

class Product
{
    private function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly float $price,
        private int $stockQuantity,
        private readonly UserId $userId,
        private StatusProduct $status = StatusProduct::ACTIVE,
        private readonly ?int $version = null,
    ) {
    }

    public static function create(
        string $id,
        string $name,
        string $description,
        float $price,
        int $stockQuantity,
        UserId $userId,
    ): self {
        return new self($id, $name, $description, $price, $stockQuantity, $userId);
    }

    public static function fromPersistence(
        string $id,
        string $name,
        string $description,
        float $price,
        int $stockQuantity,
        UserId $userId,
        StatusProduct $status,
        int $version,
    ): self {
        return new self($id, $name, $description, $price, $stockQuantity, $userId, $status, $version);
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

    public function getPrice(): float
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

    public function getVersion(): ?int
    {
        return $this->version;
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
