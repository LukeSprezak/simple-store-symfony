<?php

declare(strict_types=1);

namespace App\Order\Application\Command\AddProductToCart;

use App\Order\Domain\Exception\ProductNotFoundException;
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
            $cart = $this->cartRepository->find($command->cartId);

            if (!$cart) {
                $cart = Cart::create($command->cartId, $command->status);
            }

            $product = $this->productRepository->get($command->productId);
            $cart->addProduct($product, $command->quantity);

            $this->cartRepository->save($cart);
            $this->productRepository->save($product);
        } catch (ProductNotFoundException|ProductUnavailableException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new \RuntimeException('An unexpected error occurred while adding a product to the cart.', 0, $exception);
        }
    }
}
