<?php

declare(strict_types=1);

namespace App\Order\Application\Command\AddProductToCart;

use App\Order\Domain\Exception\CartNotActiveException;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Order\Domain\Exception\ProductUnavailableException;
use App\Order\Domain\Model\Cart;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Application\Bus\Command\Sync\CommandHandler;

final readonly class AddProductToCartCommandHandler implements CommandHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(AddProductToCartCommand $command): void
    {
        try {
            $cart = $command->createCart
                ? Cart::create($command->cartId, $command->status, $command->userId)
                : $this->cartRepository->find($command->cartId);

            if (!$cart || !$cart->isOwnedBy($command->userId)) {
                throw new CartNotFoundException($command->cartId);
            }

            $product = $this->productRepository->get($command->productId);
            $cart->addProduct($product, $command->quantity);

            $this->cartRepository->save($cart);
            $this->productRepository->save($product);
        } catch (CartNotActiveException|CartNotFoundException|ProductNotFoundException|ProductUnavailableException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new \RuntimeException('An unexpected error occurred while adding a product to the cart.', 0, $exception);
        }
    }
}
