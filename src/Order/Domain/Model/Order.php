<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Order
{
    private string $id;
    private string $status;
    private \DateTimeImmutable $createdAt;
    private Collection $items;

    private function __construct(
        string $id,
        string $status,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->items = new ArrayCollection();
    }

    public static function create(
        string $id,
        string $status,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $status, $createdAt);
    }

    public static function fromPersistence(
        string $id,
        string $status,
        \DateTimeImmutable $createdAt,
        array $items,
    ): self {
        $order = new self($id, $status, $createdAt);
        foreach ($items as $item) {
            $order->addItem($item);
        }

        return $order;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $orderItem): void
    {
        if (!$this->items->contains($orderItem)) {
            $this->items->add($orderItem);
        }
    }

    public function removeItem(OrderItem $orderItem): void
    {
        $this->items->removeElement($orderItem);
    }
}
