<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Enum\StatusOrderTransition;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Order\Domain\Exception\OrderTransitionNotAllowedException;
use App\Order\Domain\ValueObject\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

/**
 * @implements AggregateRoot<OrderId>
 */
class Order implements AggregateRoot
{
    /** @use AggregateRootBehaviour<OrderId> */
    use AggregateRootBehaviour;

    private string $status;
    private UserId $ownerId;
    private \DateTimeImmutable $createdAt;
    /** @var Collection<int, OrderItem> */
    private Collection $items;

    /**
     * @param non-empty-string $id
     * @param list<OrderItem>  $items
     */
    public static function create(
        string $id,
        string $status,
        UserId $ownerId,
        \DateTimeImmutable $createdAt,
        array $items,
    ): self {
        $order = new self(OrderId::fromString($id));
        $order->recordThat(new OrderPlaced($id, $ownerId->getId(), $status, $createdAt, array_map(
            static fn (OrderItem $item): array => [
                'id' => $item->getId(),
                'productId' => $item->getProduct()->getId(),
                'productName' => $item->getProduct()->getName(),
                'unitPrice' => $item->getProduct()->getPrice()->getAmount(),
                'quantity' => $item->getQuantity(),
            ],
            $items
        )));

        return $order;
    }

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->aggregateRootId->toString();
    }

    public function getOwnerId(): UserId
    {
        return $this->ownerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function changeStatus(StatusOrderTransition $transition): void
    {
        if (!in_array(StatusOrder::from($this->status), $transition->allowedFrom(), true)) {
            throw new OrderTransitionNotAllowedException($transition->value, $this->status);
        }

        $this->recordThat(new OrderStatusChanged($this->getId(), $transition->value, $this->status, $transition->target()->value));
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    protected function applyOrderPlaced(OrderPlaced $event): void
    {
        $this->status = $event->status;
        $this->ownerId = new UserId($event->ownerId);
        $this->createdAt = $event->createdAt;
        $this->items = new ArrayCollection(array_map(
            static fn (array $item): OrderItem => OrderItem::create($item['id'], new ProductSnapshot($item['productId'], $item['productName'], new Money($item['unitPrice'])), $item['quantity']),
            $event->items
        ));
    }

    protected function applyOrderStatusChanged(OrderStatusChanged $event): void
    {
        $this->status = $event->toStatus;
    }
}
