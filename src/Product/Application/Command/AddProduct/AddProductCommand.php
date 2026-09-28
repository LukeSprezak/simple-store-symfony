<?php

declare(strict_types=1);

namespace App\Product\Application\Command\AddProduct;

use App\Shared\Application\Bus\Command\Async\Command;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;

final readonly class AddProductCommand implements Command
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public Money $price,
        public int $stockQuantity,
        public UserId $userId,
    ) {
    }
}
