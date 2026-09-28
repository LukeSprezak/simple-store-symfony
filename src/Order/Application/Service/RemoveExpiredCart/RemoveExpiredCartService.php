<?php

declare(strict_types=1);

namespace App\Order\Application\Service\RemoveExpiredCart;

use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Service\StockReservation;
use Doctrine\ORM\EntityManagerInterface;

class RemoveExpiredCartService
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StockReservation $stockReservation,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function expireCarts(\DateTimeImmutable $now): void
    {
        $cartsToExpire = $this->cartRepository->findExpiredCarts($now);

        if (empty($cartsToExpire)) {
            return;
        }

        foreach ($cartsToExpire as $cart) {
            foreach ($cart->getActiveItems() as $item) {
                $this->stockReservation->release($item->getProduct()->getId(), $item->getQuantity());
            }

            $cart->expire();
            $cart->clearItemQuantities();
            $this->cartRepository->save($cart);
        }

        $this->entityManager->flush();
    }
}
