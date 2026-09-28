<?php

declare(strict_types=1);

namespace App\Product\Domain\Repository;

use App\Product\Domain\Model\Product;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;
    public function get(string $id): Product;
}
