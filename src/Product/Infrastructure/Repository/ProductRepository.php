<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Repository;

use App\Product\Domain\Exception\ProductNotFoundException;
use App\Product\Domain\Model\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface as ProductDomainRepository;
use App\Product\Infrastructure\Doctrine\Entity\Product as ProductEntity;
use App\Product\Infrastructure\Transformer\ProductTransformer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ProductRepository implements ProductDomainRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductTransformer $transformer,
    ) {
    }

    public function getNextId(): string
    {
        return (string) Uuid::v7();
    }

    public function findByIds(array $ids): array
    {
        $entities = $this->entityManager->getRepository(ProductEntity::class)
            ->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        return array_map(fn (ProductEntity $entity) => $this->transformer->toDomain($entity), $entities);
    }

    public function save(Product $product): void
    {
        $entity = $this->entityManager->find(ProductEntity::class, $product->getId(), LockMode::OPTIMISTIC, $product->getVersion()) ?? new ProductEntity();
        $this->transformer->fromDomain($product, $entity);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function get(string $id): Product
    {
        $productEntity = $this->entityManager->getRepository(ProductEntity::class)->findOneBy(['id' => $id]);

        if (!$productEntity) {
            throw new ProductNotFoundException($id);
        }

        return $this->transformer->toDomain($productEntity);
    }
}
