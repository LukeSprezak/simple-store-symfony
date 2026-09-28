<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

class OrderItem
{
    private string $id;
    private Order $order;
    private ProductSnapshot $product;
    private int $quantity;

    private function __construct(
        string $id,
        Order $order,
        ProductSnapshot $product,
        int $quantity,
    ) {
        $this->id = $id;
        $this->order = $order;
        $this->product = $product;
        $this->quantity = $quantity;
    }

    public static function create(
        string $id,
        Order $order,
        ProductSnapshot $product,
        int $quantity,
    ): self {
        return new self($id, $order, $product, $quantity);
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
}
