<?php

declare(strict_types=1);

namespace App\Product\Application\Command\RemoveProduct;

use App\Product\Domain\Exception\ProductRemoveException;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Application\Bus\Command\Async\CommandHandler;

final readonly class RemoveProductCommandHandler implements CommandHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @throws ProductRemoveException
     */
    public function __invoke(RemoveProductCommand $command): void
    {
        try {
            $product = $this->productRepository->get($command->id);
            $product->deactivate();
            $this->productRepository->save($product);
        } catch (\Exception $exception) {
            throw new ProductRemoveException('Failed to remove the product.', 0, $exception);
        }
    }
}
