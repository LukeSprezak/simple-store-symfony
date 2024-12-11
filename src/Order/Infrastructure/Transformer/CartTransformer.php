<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Transformer;

use App\Order\Domain\Exception\ProductNotFoundException;
use App\Order\Domain\Model\Cart as CartDomain;
use App\Order\Domain\Model\CartItem as CartItemDomain;
use App\Order\Infrastructure\Doctrine\Entity\Cart as CartEntity;
use App\Order\Infrastructure\Doctrine\Entity\CartItem as CartItemEntity;
use App\Product\Domain\Model\Product as ProductDomainModel;
use App\Product\Infrastructure\Doctrine\Entity\Product as ProductEntity;
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
        $existingItems = [];
        foreach ($entity->getItems() as $itemEntity) {
            $existingItems[$itemEntity->getId()] = $itemEntity;
        }

        foreach ($domain->getItems() as $itemDomain) {
            if (isset($existingItems[$itemDomain->getId()])) {
                $itemEntity = $existingItems[$itemDomain->getId()];
                $itemEntity->setQuantity($itemDomain->getQuantity());
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
        $domain = CartDomain::create($entity->getId());

        foreach ($entity->getItems() as $itemEntity) {
            $productDomain = ProductDomainModel::fromPersistence(
                $itemEntity->getProduct()->getId(),
                $itemEntity->getProduct()->getName(),
                $itemEntity->getProduct()->getDescription(),
                $itemEntity->getProduct()->getPrice(),
                $itemEntity->getProduct()->getStockQuantity()
            );

            $itemDomain = CartItemDomain::create(
                $itemEntity->getId(),
                $productDomain,
                $itemEntity->getQuantity()
            );

            $domain->addItem($itemDomain);
        }

        return $domain;
    }

    public function modelToEntity(CartDomain $cartDomain): CartEntity
    {
        $cartEntity = $this->entityManager->getRepository(CartEntity::class)->find($cartDomain->getId());

        if (!$cartEntity) {
            $cartEntity = new CartEntity($cartEntity->getId());
        }

        return $cartEntity;
    }
}
