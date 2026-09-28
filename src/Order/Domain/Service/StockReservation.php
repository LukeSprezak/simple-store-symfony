<?php

declare(strict_types=1);

namespace App\Order\Domain\Service;

interface StockReservation
{
    public function reserve(string $productId, int $quantity): void;

    public function release(string $productId, int $quantity): void;
}
