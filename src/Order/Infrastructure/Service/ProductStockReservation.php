<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Service;

use App\Order\Domain\Exception\ProductUnavailableException;
use App\Order\Domain\Service\StockReservation;
use App\Product\Domain\Enum\StatusProduct;
use App\Product\Domain\Repository\ProductRepositoryInterface;

final readonly class ProductStockReservation implements StockReservation
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function reserve(string $productId, int $quantity): void
    {
        $product = $this->productRepository->get($productId);

        if (StatusProduct::ACTIVE !== $product->getStatus() || $product->getStockQuantity() < $quantity) {
            throw new ProductUnavailableException($productId);
        }

        $product->decreaseStock($quantity);
        $this->productRepository->save($product);
    }

    public function release(string $productId, int $quantity): void
    {
        $product = $this->productRepository->get($productId);
        $product->increaseStock($quantity);
        $this->productRepository->save($product);
    }
}
