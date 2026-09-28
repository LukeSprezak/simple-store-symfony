<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Repository;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Shared\Application\Bus\Event\EventBus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CartRepository implements CartRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventBus $eventBus,
    ) {
    }

    public function find(string $id): ?Cart
    {
        return $this->entityManager->find(Cart::class, $id);
    }

    public function save(Cart $cart): void
    {
        $this->entityManager->persist($cart);

        $this->eventBus->publish(...$cart->pullDomainEvents());
    }

    public function findExpiredCarts(\DateTimeImmutable $now): array
    {
        return $this->entityManager->getRepository(Cart::class)->createQueryBuilder('c')
            ->where('c.expiresAt <= :now')
            ->andWhere('c.status = :status')
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('status', StatusCart::ACTIVE)
            ->getQuery()
            ->getResult();
    }
}
