<?php

declare(strict_types=1);

namespace App\Order\Application\Command\RemoveProductFromCart;

use App\Order\Domain\Exception\CartNotFoundException;
use App\Order\Domain\Exception\ProductNotFoundException;
use App\Order\Domain\Exception\ProductRemoveFromCartException;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Application\Bus\Command\Sync\CommandHandler;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class RemoveProductFromCartCommandHandler implements CommandHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(RemoveProductFromCartCommand $command): void
    {
        $cart = $this->cartRepository->find($command->cartId);
        if (!$cart) {
            throw new CartNotFoundException($command->cartId);
        }

        if ($cart->isDeleted()) {
            throw new AccessDeniedHttpException('Access to this resource is locked.');
        }

        try {
            $product = $this->productRepository->get($command->productId);
            $cart->removeProduct($product);
        } catch (ProductNotFoundException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new ProductRemoveFromCartException($command->productId, 'Cannot remove product: '.$exception->getMessage());
        }

        $this->cartRepository->save($cart);
    }
}
