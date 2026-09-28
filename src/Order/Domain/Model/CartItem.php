<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Order\Domain\Exception\InvalidQuantityException;
use Symfony\Component\Uid\Uuid;

class CartItem
{
    private function __construct(
        private readonly string $id,
        private readonly Cart $cart,
        private readonly ProductSnapshot $product,
        private int $quantity,
        private ?\DateTimeImmutable $deletedAt = null,
    ) {
        $this->validateQuantity($quantity);
    }

    public static function create(
        string $id,
        Cart $cart,
        ProductSnapshot $product,
        int $quantity,
    ): self {
        return new self($id, $cart, $product, $quantity);
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
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

    public function toOrderItem(Order $order): OrderItem
    {
        return OrderItem::create(
            Uuid::v7()->toRfc4122(),
            $order,
            $this->product,
            $this->quantity
        );
    }
}
