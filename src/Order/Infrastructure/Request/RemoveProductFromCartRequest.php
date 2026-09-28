<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Request;

use App\Order\Application\Command\RemoveProductFromCart\RemoveProductFromCartCommand;
use App\User\Domain\ValueObject\UserId;
use Symfony\Component\Validator\Constraints as Assert;

final class RemoveProductFromCartRequest
{
    #[Assert\NotBlank]
    public string $cartId;

    #[Assert\NotBlank]
    public string $productId;

    public function toCommand(string $userId): RemoveProductFromCartCommand
    {
        return new RemoveProductFromCartCommand(
            $this->cartId,
            $this->productId,
            new UserId($userId),
        );
    }
}
