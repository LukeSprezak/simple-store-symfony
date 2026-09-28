<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Enum\StatusOrderTransition;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Exception\OrderTransitionNotAllowedException;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\User\Domain\ValueObject\UserId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Order extends AggregateRoot
{
    private string $id;
    private string $status;
    private UserId $ownerId;
    private \DateTimeImmutable $createdAt;
    private Collection $items;

    private function __construct(
        string $id,
        string $status,
        UserId $ownerId,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->ownerId = $ownerId;
        $this->createdAt = $createdAt;
        $this->items = new ArrayCollection();
    }

    public static function create(
        string $id,
        string $status,
        UserId $ownerId,
        \DateTimeImmutable $createdAt,
    ): self {
        $order = new self($id, $status, $ownerId, $createdAt);
        $order->recordThat(new OrderPlaced($id, $ownerId->getId()));

        return $order;
    }

    public static function fromPersistence(
        string $id,
        string $status,
        UserId $ownerId,
        \DateTimeImmutable $createdAt,
        array $items,
    ): self {
        $order = new self($id, $status, $ownerId, $createdAt);
        foreach ($items as $item) {
            $order->addItem($item);
        }

        return $order;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOwnerId(): UserId
    {
        return $this->ownerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function apply(StatusOrderTransition $transition): void
    {
        if (!in_array(StatusOrder::from($this->status), $transition->allowedFrom(), true)) {
            throw new OrderTransitionNotAllowedException($transition->value, $this->status);
        }

        $this->status = $transition->target()->value;
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
