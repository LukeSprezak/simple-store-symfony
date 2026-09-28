<?php

declare(strict_types=1);

namespace App\Order\Domain\Repository;

use App\Order\Domain\Model\Order;

interface OrderRepositoryInterface
{
    /**
     * @param non-empty-string $id
     */
    public function find(string $id): ?Order;

    public function save(Order $order): void;
}
