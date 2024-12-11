<?php

declare(strict_types=1);

namespace App\Order\Domain\Repository;

use App\Order\Domain\Model\Cart;

interface CartRepositoryInterface
{
    public function find(string $id): ?Cart;

    public function save(Cart $cart): void;

    public function findExpiredCarts(\DateTimeImmutable $now): array;
}
