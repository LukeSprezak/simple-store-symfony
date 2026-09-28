<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Repository;

use App\Product\Domain\Exception\ProductNotFoundException;
use App\Product\Domain\Model\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface as ProductDomainRepository;
use App\Shared\Application\Bus\Event\EventBus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProductRepository implements ProductDomainRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventBus $eventBus,
    ) {
    }

    public function save(Product $product): void
    {
        $this->entityManager->persist($product);

        $this->eventBus->publish(...$product->pullDomainEvents());
    }

    public function get(string $id): Product
    {
        return $this->entityManager->find(Product::class, $id) ?? throw new ProductNotFoundException($id);
    }
}
