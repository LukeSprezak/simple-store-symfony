<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Doctrine\Entity;

use App\Product\Domain\Model\Product;
use App\Shared\Infrastructure\Doctrine\Entity\SoftDeleteTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class CartItem
{
    use SoftDeleteTrait;

    #[Id]
    #[Column(type: Types::GUID)]
    private string $id;

    #[ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    private ?Cart $cart;

    #[ManyToOne(targetEntity: Product::class)]
    private Product $product;

    #[Column(type: Types::INTEGER)]
    private int $quantity;

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function increaseQuantity(int $quantity): void
    {
        $this->quantity += $quantity;
    }
}
