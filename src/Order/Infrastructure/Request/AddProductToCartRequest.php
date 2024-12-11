<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Request;

use App\Order\Application\Command\AddProductToCart\AddProductToCartCommand;
use Symfony\Component\Validator\Constraints as Assert;

final class AddProductToCartRequest
{
    public ?string $cartId = null;

    #[Assert\NotBlank]
    public string $productId;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\GreaterThanOrEqual(1)]
    public int $quantity;

    public function toCommand(): AddProductToCartCommand
    {
        return new AddProductToCartCommand(
            $this->cartId,
            $this->productId,
            $this->quantity
        );
    }
}
