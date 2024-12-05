<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Http\Request;

use App\Product\Application\Command\AddProduct\AddProductCommand;
use App\User\Domain\ValueObject\UserId;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Type;

final class AddProductRequest
{
    #[NotBlank]
    #[Length(min: 2, max: 255)]
    public string $name;

    #[NotBlank]
    #[Length(min: 2, max: 2000)]
    public string $description;

    #[NotBlank]
    #[Type('numeric')]
    #[GreaterThan(0)]
    public float $price;

    #[NotBlank]
    #[PositiveOrZero]
    public int $stockQuantity;

    public function toCommand(string $userId): AddProductCommand
    {
        return new AddProductCommand(
            $this->name,
            $this->description,
            $this->price,
            $this->stockQuantity,
            new UserId($userId)
        );
    }
}
