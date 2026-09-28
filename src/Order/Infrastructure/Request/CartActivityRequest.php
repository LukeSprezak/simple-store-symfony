<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CartActivityRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 50,
        #[Assert\Uuid]
        public ?string $after = null,
    ) {
    }
}
