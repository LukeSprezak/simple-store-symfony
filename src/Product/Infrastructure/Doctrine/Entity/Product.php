<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Doctrine\Entity;

use App\Product\Domain\Enum\StatusProduct;
use App\User\Infrastructure\Doctrine\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Version;

#[Entity]
class Product
{
    #[Id]
    #[Column(type: Types::GUID)]
    private string $id;

    #[Version]
    #[Column(type: Types::INTEGER)]
    private int $version = 1;

    #[Column(type: Types::INTEGER)]
    private int $salesCount = 0;

    #[Column(type: Types::STRING, length: 255)]
    private string $name;

    #[Column(type: Types::TEXT, length: 2000)]
    private ?string $description;

    #[Column(type: Types::INTEGER)]
    private int $price;

    #[Column(type: Types::INTEGER)]
    private int $stockQuantity;

    #[OneToMany(targetEntity: Review::class, mappedBy: 'product', cascade: ['persist', 'remove'])]
    private Collection $reviews;

    #[Column(type: Types::STRING, length: 50, enumType: StatusProduct::class)]
    private StatusProduct $status;

    #[ManyToOne(targetEntity: User::class, cascade: ['persist'])]
    #[JoinColumn(nullable: false)]
    private User $user;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getSalesCount(): int
    {
        return $this->salesCount;
    }

    public function setSalesCount(int $salesCount): void
    {
        $this->salesCount = $salesCount;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): void
    {
        $this->price = $price;
    }

    public function getStatus(): StatusProduct
    {
        return $this->status;
    }

    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    public function setStockQuantity(int $stockQuantity): void
    {
        $this->stockQuantity = $stockQuantity;
    }

    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function increaseStock(int $quantity): void
    {
        $this->stockQuantity += $quantity;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function setStatus(StatusProduct $status): void
    {
        $this->status = $status;
    }
}
