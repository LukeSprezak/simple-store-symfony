<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Repository;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Model\Cart as CartDomain;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Infrastructure\Doctrine\Entity\Cart as CartEntity;
use App\Order\Infrastructure\Transformer\CartTransformer;
use App\Shared\Application\Bus\Event\EventBus;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CartRepository implements CartRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventBus $eventBus,
        private CartTransformer $transformer,
    ) {
    }

    public function find(string $id): ?CartDomain
    {
        $entity = $this->entityManager->getRepository(CartEntity::class)->find($id);

        return $entity ? $this->transformer->toDomain($entity) : null;
    }

    public function save(CartDomain $cart): void
    {
        $entity = $this->entityManager->getRepository(CartEntity::class)->find($cart->getId()) ?? new CartEntity($cart->getId());
        $this->transformer->fromDomain($cart, $entity);

        $this->entityManager->persist($entity);

        $this->eventBus->publish(...$cart->pullDomainEvents());
    }

    public function findExpiredCarts(\DateTimeImmutable $now): array
    {
        $entities = $this->entityManager->getRepository(CartEntity::class)->createQueryBuilder('c')
            ->where('c.expiresAt <= :now')
            ->andWhere('c.status = :status')
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('status', StatusCart::ACTIVE->value, ParameterType::STRING)
            ->getQuery()
            ->getResult();

        return array_map(fn (CartEntity $entity) => $this->transformer->toDomain($entity), $entities);
    }
}
