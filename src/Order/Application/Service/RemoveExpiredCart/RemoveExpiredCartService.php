<?php

declare(strict_types=1);

namespace App\Order\Application\Service\RemoveExpiredCart;

use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class RemoveExpiredCartService
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function expireCarts(\DateTimeImmutable $now): void
    {
        $cartsToExpire = $this->cartRepository->findExpiredCarts($now);

        if (empty($cartsToExpire)) {
            return;
        }

        $productQuantities = [];
        foreach ($cartsToExpire as $cart) {
            foreach ($cart->getItems() as $item) {
                $productId = $item->getProduct()->getId();
                $quantity = $item->getQuantity();
                $productQuantities[$productId] = ($productQuantities[$productId] ?? 0) + $quantity;
            }

            $cart->expire();
            $cart->clearItemQuantities();
            $this->cartRepository->save($cart);
        }

        if (!empty($productQuantities)) {
            $productIds = array_keys($productQuantities);
            $products = $this->productRepository->findByIds($productIds);

            foreach ($products as $product) {
                $productId = $product->getId();
                $quantity = $productQuantities[$productId] ?? 0;

                if ($quantity > 0) {
                    $product->increaseStock($quantity);
                    $this->productRepository->save($product);
                }
            }
        }

        $this->entityManager->flush();
    }
}
