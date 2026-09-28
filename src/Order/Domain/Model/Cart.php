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
use App\Order\Domain\Exception\CartQuantityLimitExceededException;
use App\Order\Domain\Exception\CartTransitionNotAllowedException;
use App\Order\Domain\Exception\InvalidQuantityException;
use App\Order\Domain\Exception\ProductNotInCartException;
use App\Order\Domain\Policy\CartLimits;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Money;
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
    /** @var Collection<int, CartItem> */
    // not readonly: Doctrine swaps it for a PersistentCollection when a new cart is persisted
    private Collection $items;

    /**
     * @param Collection<int, CartItem>|null $items
     */
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
        $createdAt = new \DateTimeImmutable();
        $cart = new self(
            id: $id,
            status: $status,
            ownerId: $ownerId,
            createdAt: $createdAt,
            expiresAt: $createdAt->modify('+24 hours')
        );
        $cart->recordThat(new CartCreated($id, $ownerId->getId()));

        return $cart;
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

    /**
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getActiveItems(): Collection
    {
        return $this->items->filter(static fn (CartItem $item) => !$item->isDeleted());
    }

    public function removeProduct(string $productId): int
    {
        $this->assertActive();

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
        $this->assertCanAddProduct($product->getId(), $quantity);
        $existingItem = $this->findItemByProductId($product->getId());

        if ($existingItem) {
            $existingItem->increaseQuantity($quantity);
        } else {
            $itemId = Uuid::v4()->toRfc4122();
            $cartItem = CartItem::create($itemId, $this, $product, $quantity);
            $this->items->add($cartItem);
        }

        $this->recordThat(new ProductAddedToCart($this->id, $product->getId(), $quantity));
    }

    public function assertCanAddProduct(string $productId, int $quantity): void
    {
        $this->assertActive();

        if ($quantity <= 0) {
            throw new InvalidQuantityException('The quantity must be a positive number.');
        }

        $currentQuantity = $this->findItemByProductId($productId)?->getQuantity() ?? 0;
        // Subtract before comparing so PHP_INT_MAX cannot overflow during addition.
        if ($quantity > CartLimits::MAX_QUANTITY_PER_PRODUCT - $currentQuantity) {
            throw new CartQuantityLimitExceededException($productId);
        }
    }

    public function getTotalAmount(): Money
    {
        return array_reduce(
            array: $this->getActiveItems()->toArray(),
            callback: static fn (Money $total, CartItem $item) => $total->add($item->getProduct()->getPrice()->multiply($item->getQuantity())),
            initial: new Money(0)
        );
    }

    public function convert(): void
    {
        if (!in_array($this->status, [StatusCart::ACTIVE, StatusCart::ABANDONED], true)) {
            throw new CartTransitionNotAllowedException('convert', $this->status->value);
        }

        if ($this->isExpired()) {
            throw new CartNotActiveException($this->id);
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
        return new \DateTimeImmutable() >= $this->expiresAt;
    }

    public function assertActive(): void
    {
        if (StatusCart::ACTIVE !== $this->status || $this->isExpired()) {
            throw new CartNotActiveException($this->id);
        }
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
