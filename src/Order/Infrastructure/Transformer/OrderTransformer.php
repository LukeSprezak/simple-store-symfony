<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Transformer;

use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Model\Order as DomainOrder;
use App\Order\Domain\Model\OrderItem as DomainOrderItem;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Infrastructure\Doctrine\Entity\Order as EntityOrder;
use App\Order\Infrastructure\Doctrine\Entity\OrderItem as EntityOrderItem;
use App\Product\Domain\Model\Product as ProductEntity;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrderTransformer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function fromDomain(DomainOrder $domainOrder, EntityOrder $entityOrder): void
    {
        $statusEnum = StatusOrder::from($domainOrder->getStatus());

        $entityOrder->setId($domainOrder->getId());
        $entityOrder->setStatus($statusEnum);
        $entityOrder->setOwnerId($domainOrder->getOwnerId()->getId());
        $entityOrder->setCreatedAt($domainOrder->getCreatedAt());

        $existingItems = [];
        foreach ($entityOrder->getItems() as $itemEntity) {
            $existingItems[$itemEntity->getId()] = $itemEntity;
        }

        foreach ($domainOrder->getItems() as $itemDomain) {
            if (isset($existingItems[$itemDomain->getId()])) {
                $itemEntity = $existingItems[$itemDomain->getId()];
                $itemEntity->setProduct($this->entityManager->getRepository(ProductEntity::class)->find($itemDomain->getProduct()->getId()));
                $itemEntity->setQuantity($itemDomain->getQuantity());
                unset($existingItems[$itemDomain->getId()]);
            } else {
                $productEntity = $this->entityManager->getRepository(ProductEntity::class)
                    ->find($itemDomain->getProduct()->getId());

                if (!$productEntity) {
                    throw new \InvalidArgumentException("Product with ID {$itemDomain->getProduct()->getId()} not found.");
                }

                $itemEntity = new EntityOrderItem($itemDomain->getId(), $itemDomain->getQuantity());
                $itemEntity->setProduct($productEntity);
                $itemEntity->setQuantity($itemDomain->getQuantity());
                $itemEntity->setUnitPrice($itemDomain->getProduct()->getPrice()->getAmount());
                $itemEntity->setOrder($entityOrder);

                $entityOrder->addItem($itemEntity);
            }
        }

        foreach ($existingItems as $itemEntity) {
            $entityOrder->removeItem($itemEntity);
            $this->entityManager->remove($itemEntity);
        }
    }

    public function toDomain(EntityOrder $entityOrder): DomainOrder
    {
        $domainOrder = DomainOrder::fromPersistence(
            $entityOrder->getId(),
            $entityOrder->getStatus(),
            new UserId($entityOrder->getOwnerId()),
            $entityOrder->getCreatedAt(),
            []
        );

        foreach ($entityOrder->getItems() as $itemEntity) {
            $product = $itemEntity->getProduct();
            $domainOrderItem = DomainOrderItem::fromPersistence(
                $itemEntity->getId(),
                new ProductSnapshot($product->getId(), $product->getName(), new Money($itemEntity->getUnitPrice())),
                $itemEntity->getQuantity()
            );
            $domainOrder->addItem($domainOrderItem);
        }

        return $domainOrder;
    }
}
