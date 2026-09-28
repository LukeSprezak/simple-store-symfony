<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Event\CartConverted;
use App\Order\Domain\Event\CartCreated;
use App\Order\Domain\Event\CartExpired;
use App\Order\Domain\Event\ProductAddedToCart;
use App\Order\Domain\Event\ProductRemovedFromCart;
use App\Order\Domain\Exception\CartNotActiveException;
use App\Order\Domain\Exception\CartTransitionNotAllowedException;
use App\Order\Domain\Exception\ProductNotInCartException;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\User\Domain\ValueObject\UserId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;

class Cart extends AggregateRoot
{
    private readonly string $id;
    private StatusCart $status;
    private readonly UserId $ownerId;
    private readonly \DateTimeImmutable $createdAt;
    private readonly \DateTimeImmutable $expiresAt;
    private readonly Collection $items;

    public function __construct(
        string $id,
        StatusCart $status,
        UserId $ownerId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $expiresAt,
        ?Collection $items = null,
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->ownerId = $ownerId;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->items = $items ?? new ArrayCollection();
    }

    public static function create(string $id, StatusCart $status, UserId $ownerId): self
    {
        $cart = new self(
            id: $id,
            status: $status,
            ownerId: $ownerId,
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable()->modify('+24 hours')
        );
        $cart->recordThat(new CartCreated($id, $ownerId->getId()));

        return $cart;
    }

    public static function fromPersistence(
        string $id,
        StatusCart $status,
        UserId $ownerId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $expiresAt,
        array $items,
    ): self {
        return new self(
            id: $id,
            status: $status,
            ownerId: $ownerId,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            items: new ArrayCollection($items)
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOwnerId(): UserId
    {
        return $this->ownerId;
    }

    public function isOwnedBy(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    public function getStatus(): StatusCart
    {
        return $this->status;
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

    public function getActiveItems(): Collection
    {
        return $this->items->filter(static fn (CartItem $item) => !$item->isDeleted());
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

    public function removeProduct(string $productId): int
    {
        $item = $this->findItemByProductId($productId);

        if (!$item) {
            throw new ProductNotInCartException($productId);
        }

        $item->softDelete();
        $this->recordThat(new ProductRemovedFromCart($this->id, $productId, $item->getQuantity()));

        return $item->getQuantity();
    }

    public function addProduct(ProductSnapshot $product, int $quantity): void
    {
        if (StatusCart::ACTIVE !== $this->status || $this->isExpired()) {
            throw new CartNotActiveException($this->id);
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('The quantity must be a positive number.');
        }

        $existingItem = $this->findItemByProductId($product->getId());

        if ($existingItem) {
            $existingItem->increaseQuantity($quantity);
        } else {
            $itemId = Uuid::v4()->toRfc4122();
            $cartItem = CartItem::create($itemId, $product, $quantity);
            $this->items->add($cartItem);
        }

        $this->recordThat(new ProductAddedToCart($this->id, $product->getId(), $quantity));
    }

    public function getTotalAmount(): float
    {
        return array_reduce(
            array: $this->items->toArray(),
            callback: static fn (float $total, CartItem $item) => $total + ($item->getProduct()->getPrice() * $item->getQuantity()),
            initial: 0.0
        );
    }

    public function convert(): void
    {
        if (!in_array($this->status, [StatusCart::ACTIVE, StatusCart::ABANDONED], true)) {
            throw new CartTransitionNotAllowedException('convert', $this->status->value);
        }

        $this->status = StatusCart::CONVERTED_TO_ORDER;
        $this->recordThat(new CartConverted($this->id));
    }

    public function isEmpty(): bool
    {
        return $this->getActiveItems()->isEmpty();
    }

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() > $this->expiresAt;
    }

    public function expire(): void
    {
        $this->status = StatusCart::EXPIRED;
        $this->recordThat(new CartExpired($this->id));
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
            if ($item->getProduct()->getId() === $productId && !$item->isDeleted()) {
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
