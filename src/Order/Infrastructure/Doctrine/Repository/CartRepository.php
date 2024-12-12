<?php

namespace App\Order\Infrastructure\Doctrine\Repository;

use App\Order\Infrastructure\Doctrine\Entity\Cart;
use Doctrine\ORM\EntityManagerInterface;

class CartRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(string $id): ?Cart
    {
        return $this->entityManager->getRepository(Cart::class)->find($id);
    }

    public function save(Cart $cart): void
    {
        $this->entityManager->persist($cart);
        $this->entityManager->flush();
    }
}
