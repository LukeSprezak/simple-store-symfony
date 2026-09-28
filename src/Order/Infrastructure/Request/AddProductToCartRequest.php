<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Request;

use App\Order\Application\Command\AddProductToCart\AddProductToCartCommand;
use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Policy\CartLimits;
use App\User\Domain\ValueObject\UserId;
use Symfony\Component\Validator\Constraints as Assert;

final class AddProductToCartRequest
{
    #[Assert\NotBlank]
    public string $productId;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\GreaterThanOrEqual(1)]
    #[Assert\LessThanOrEqual(CartLimits::MAX_QUANTITY_PER_PRODUCT)]
    public int $quantity;

    public StatusCart $status;

    public function toCommand(string $cartId, bool $createCart, string $userId): AddProductToCartCommand
    {
        return new AddProductToCartCommand(
            $cartId,
            $this->productId,
            $this->quantity,
            StatusCart::ACTIVE,
            new UserId($userId),
            $createCart,
        );
    }
}
