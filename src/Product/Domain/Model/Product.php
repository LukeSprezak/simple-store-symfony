<?php

declare(strict_types=1);

namespace App\Product\Domain\Model;

use App\Product\Domain\Enum\StatusProduct;
use App\User\Domain\Model\User;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

class Product
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly float $price,
        private int $stockQuantity,
        private readonly User $user,
        protected readonly StatusProduct $status = StatusProduct::ACTIVE,
    ) {
    }

    public static function create(
        string $name,
        string $description,
        float $price,
        int $stockQuantity,
        User $user
    ): self {
        $id = Uuid::v7()->toRfc4122();
        return new self($id, $name, $description, $price, $stockQuantity, $user);
    }

    public static function fromPersistence(
        string $id,
        string $name,
        string $description,
        float $price,
        int $stockQuantity,
        User $user,
        StatusProduct $status = StatusProduct::ACTIVE,
    ): self {
        return new self($id, $name, $description, $price, $stockQuantity, $user, $status);
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): StatusProduct
    {
        return $this->status;
    }

    public function decreaseStock(int $quantity): void
    {
        if ($quantity > $this->stockQuantity) {
            throw new InvalidArgumentException('The number of products is insufficient: ' . $this->name);
        }

        $this->stockQuantity -= $quantity;
    }
}
