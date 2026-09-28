<?php

declare(strict_types=1);

namespace App\Product\Application\Command\AddProduct;

use App\Product\Domain\Exception\ProductCreateException;
use App\Product\Domain\Model\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Application\Bus\Command\Async\CommandHandler;

final readonly class AddProductCommandHandler implements CommandHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(AddProductCommand $command): void
    {
        try {
            $product = Product::create($command->id, $command->name, $command->description, $command->price, $command->stockQuantity, $command->userId);
            $this->productRepository->save($product);
        } catch (ProductCreateException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new \RuntimeException('An unexpected error occurred while adding the product.', 0, $exception);
        }
    }
}
