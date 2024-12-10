<?php

declare(strict_types=1);

namespace App\Product\Application\Command\RemoveProduct;

use App\Shared\Application\Bus\Command\Async\Command;

final class RemoveProductCommand implements Command
{
    public function __construct(
        public string $id,
    ) {
    }
}
