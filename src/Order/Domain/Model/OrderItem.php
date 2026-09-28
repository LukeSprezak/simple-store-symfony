<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;


class OrderItem
{
    private string $id;
    private ProductSnapshot $product;
    private int $quantity;

    private function __construct(
        string $id,
        ProductSnapshot $product,
        int $quantity,
    ) {
        $this->id = $id;
        $this->product = $product;
        $this->quantity = $quantity;
    }

    public static function create(
        string $id,
        ProductSnapshot $product,
        int $quantity,
    ): self {
        return new self($id, $product, $quantity);
    }

    public static function fromPersistence(
        string $id,
        ProductSnapshot $product,
        int $quantity,
    ): self {
        return new self($id, $product, $quantity);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProduct(): ProductSnapshot
    {
        return $this->product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function toOrderItem(): OrderItem
    {
        return self::create(
            $this->id,
            $this->product,
            $this->quantity
        );
    }
}
