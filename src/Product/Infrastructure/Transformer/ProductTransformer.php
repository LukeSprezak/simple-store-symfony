<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Transformer;

use App\Product\Domain\Model\Product;
use App\Product\Infrastructure\Doctrine\Entity\Product as ProductEntity;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityNotFoundException;

final readonly class ProductTransformer
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function fromDomain(Product $product, ?ProductEntity $existingEntity = null): ProductEntity
    {
        if ($existingEntity) {
            $productEntity = $existingEntity;
        } else {
            $productEntity = new ProductEntity();
        }

        $productEntity->setId($product->getId());
        $productEntity->setName($product->getName());
        $productEntity->setDescription($product->getDescription());
        $productEntity->setPrice($product->getPrice());
        $productEntity->setStockQuantity($product->getStockQuantity());
        $productEntity->setStatus($product->getStatus());

        $userId = $product->getUserId()->getId();
        $user = $this->userRepository->findEntityById($userId);

        if (!$user) {
            throw new EntityNotFoundException("User with ID {$userId} not found.");
        }

        $productEntity->setUser($user);

        return $productEntity;
    }

    public function toDomain(ProductEntity $productEntity): Product
    {
        $userId = new UserId($productEntity->getUser()->getId());

        return Product::fromPersistence(
            $productEntity->getId(),
            $productEntity->getName(),
            $productEntity->getDescription(),
            $productEntity->getPrice(),
            $productEntity->getStockQuantity(),
            $userId,
            $productEntity->getStatus(),
            $productEntity->getVersion(),
        );
    }
}
