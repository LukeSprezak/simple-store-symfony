<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Order\Domain\Exception\InvalidQuantityException;
use App\Product\Domain\Model\Product;

final class CartItem
{
    private function __construct(
        private readonly string $id,
        private readonly Product $product,
        private int $quantity,
    ) {
        $this->validateQuantity($quantity);
    }

    public static function create(
        string $id,
        Product $product,
        int $quantity,
    ): self {
        return new self($id, $product, $quantity);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function increaseQuantity(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidQuantityException('The quantity to be increased must be a positive number.');
        }

        $this->quantity += $amount;
    }

    public function decreaseQuantity(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidQuantityException('The quantity to be decreased must be a positive number.');
        }

        if ($amount > $this->quantity) {
            throw new InvalidQuantityException('Cannot decrease quantity below zero.');
        }

        $this->quantity -= $amount;
    }

    public function setQuantity(int $quantity): void
    {
        $this->validateQuantity($quantity);
        $this->quantity = $quantity;
    }

    private function validateQuantity(int $quantity): void
    {
        if ($quantity < 0) {
            throw new InvalidQuantityException('The quantity cannot be negative.');
        }
    }

    public function toOrderItem(): OrderItem
    {
        return OrderItem::create(
            $this->id,
            $this->product,
            $this->quantity
        );
    }
}
