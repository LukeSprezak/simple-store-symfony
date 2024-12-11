<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\ProductNotInCartException;
use App\Order\Domain\Exception\ProductUnavailableException;
use App\Product\Domain\Model\Product;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;

class Cart
{
    private readonly string $id;
    private StatusCart $status;
    private readonly \DateTimeImmutable $createdAt;
    private readonly \DateTimeImmutable $expiresAt;
    private readonly Collection $items;

    public function __construct(
        string $id,
        StatusCart $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $expiresAt,
        ?Collection $items = null,
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->items = $items ?? new ArrayCollection();
    }

    public static function create(string $id): self
    {
        return new self(
            id: $id,
            status: StatusCart::ACTIVE,
            createdAt: new \DateTimeImmutable(),
            expiresAt: (new \DateTimeImmutable())->modify('+24 hours')
        );
    }

    public static function fromPersistence(
        string $id,
        StatusCart $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $expiresAt,
        array $items,
    ): self {
        return new self(
            id: $id,
            status: $status,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            items: new ArrayCollection($items)
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): StatusCart
    {
        return $this->status;
    }

    public function setStatus(StatusCart $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(CartItem $item): void
    {
        $existingItem = $this->findItemByProductId($item->getProduct()->getId());

        if ($existingItem) {
            $existingItem->increaseQuantity($item->getQuantity());
        } else {
            $this->items->add($item);
        }
    }

    public function removeProduct(Product $product): void
    {
        $item = $this->findItemByProductId($product->getId());

        if ($item) {
            $this->items->removeElement($item);
        } else {
            throw new ProductNotInCartException($product->getId());
        }
    }

    public function addProduct(Product $product, int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('The quantity must be a positive number.');
        }

        if ($product->getStockQuantity() < $quantity) {
            throw new ProductUnavailableException('Not enough stock for product: '.$product->getName());
        }

        $existingItem = $this->findItemByProductId($product->getId());

        if ($existingItem) {
            $existingItem->increaseQuantity($quantity);
        } else {
            $itemId = Uuid::v4()->toRfc4122();
            $cartItem = CartItem::create($itemId, $product, $quantity);
            $this->items->add($cartItem);
        }

        $product->decreaseStock($quantity);
    }

    public function getTotalAmount(): float
    {
        return array_reduce(
            array: $this->items->toArray(),
            callback: static fn (float $total, CartItem $item) => $total + ($item->getProduct()->getPrice() * $item->getQuantity()),
            initial: 0.0
        );
    }

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() > $this->expiresAt;
    }

    public function expire(): void
    {
        $this->status = StatusCart::EXPIRED;
    }

    public function clearItems(): void
    {
        $this->items->clear();
    }

    public function clearItemQuantities(): void
    {
        foreach ($this->items as $item) {
            $item->setQuantity(0);
        }
    }

    private function findItemByProductId(string $productId): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getProduct()->getId() === $productId) {
                return $item;
            }
        }

        return null;
    }

    public function getItemById(string $itemId): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $itemId) {
                return $item;
            }
        }

        return null;
    }
}
