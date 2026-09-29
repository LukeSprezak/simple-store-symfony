<?php

declare(strict_types=1);

namespace App\Product\Domain\Model;

use App\Product\Domain\Enum\StatusProduct;
use App\Product\Domain\Event\StockReleased;
use App\Product\Domain\Event\StockReserved;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;

class Product extends AggregateRoot
{
    private int $version = 1;

    private function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly Money $price,
        private int $stockQuantity,
        private readonly UserId $userId,
        private readonly ?string $categoryId,
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
        ?string $categoryId = null,
    ): self {
        return new self($id, $name, $description, $price, $stockQuantity, $userId, $categoryId);
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
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
        $this->recordThat(new StockReleased($this->id, $quantity));
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
        $this->recordThat(new StockReserved($this->id, $quantity));
    }
}
