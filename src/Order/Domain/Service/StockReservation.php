<?php

declare(strict_types=1);

namespace App\Order\Domain\Service;

use App\Order\Domain\Model\ProductSnapshot;

interface StockReservation
{
    public function reserve(string $productId, int $quantity): ProductSnapshot;

    public function release(string $productId, int $quantity): void;
}
