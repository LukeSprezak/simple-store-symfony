<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Transformer;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Exception\ProductNotFoundException;
use App\Order\Domain\Model\Cart as CartDomain;
use App\Order\Domain\Model\CartItem as CartItemDomain;
use App\Order\Infrastructure\Doctrine\Entity\Cart as CartEntity;
use App\Order\Infrastructure\Doctrine\Entity\CartItem as CartItemEntity;
use App\Product\Domain\Model\Product as ProductDomainModel;
use App\Product\Infrastructure\Doctrine\Entity\Product as ProductEntity;
use App\User\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CartTransformer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function fromDomain(CartDomain $domain, CartEntity $entity): void
    {
        $entity->setStatus($domain->getStatus());
        $entity->setOwnerId($domain->getOwnerId()->getId());
        $existingItems = [];
        foreach ($entity->getItems() as $itemEntity) {
            $existingItems[$itemEntity->getId()] = $itemEntity;
        }

        foreach ($domain->getItems() as $itemDomain) {
            if (isset($existingItems[$itemDomain->getId()])) {
                $itemEntity = $existingItems[$itemDomain->getId()];
                $itemEntity->setQuantity($itemDomain->getQuantity());
                $itemEntity->setDeleted($itemDomain->isDeleted());
                $itemEntity->setDeletedAt($itemDomain->getDeletedAt());
                unset($existingItems[$itemDomain->getId()]);
            } else {
                $productEntity = $this->entityManager->getRepository(ProductEntity::class)
                    ->find($itemDomain->getProduct()->getId());

                if (!$productEntity) {
                    throw new ProductNotFoundException('Product not found: '.$itemDomain->getProduct()->getId());
                }

                $itemEntity = new CartItemEntity();
                $itemEntity->setId($itemDomain->getId());
                $itemEntity->setProduct($productEntity);
                $itemEntity->setQuantity($itemDomain->getQuantity());
                $itemEntity->setCart($entity);
                $itemEntity->setDeleted($domain->isDeleted());
                $itemEntity->setDeletedAt($domain->getDeletedAt());

                $entity->addItem($itemEntity);
            }
        }

        foreach ($existingItems as $itemEntity) {
            $itemDomain = $domain->getItemById($itemEntity->getId());
            if (null === $itemDomain) {
                $entity->removeItem($itemEntity);
            }
        }
    }

    public function toDomain(CartEntity $entity): CartDomain
    {
        $domain = CartDomain::create($entity->getId(), $entity->getStatus(), new UserId($entity->getOwnerId()));
        $statusEnum = StatusCart::from($domain->getStatus()->value);
        $entity->setStatus($statusEnum);

        foreach ($entity->getItems() as $itemEntity) {
            $product = $itemEntity->getProduct();
            $productDomain = ProductDomainModel::fromPersistence(
                $product->getId(),
                $product->getName(),
                $product->getDescription(),
                $product->getPrice(),
                $product->getStockQuantity(),
                new UserId($product->getUser()->getId())
            );

            $itemDomain = CartItemDomain::create(
                $itemEntity->getId(),
                $productDomain,
                $itemEntity->getQuantity()
            );

            $domain->setDeleted($itemEntity->isDeleted());
            $domain->setDeletedAt($itemEntity->getDeletedAt());
            $domain->addItem($itemDomain);
        }

        return $domain;
    }
}
