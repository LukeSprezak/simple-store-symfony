<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Infrastructure\Transformer;

use App\Product\Domain\Enum\StatusProduct;
use App\Product\Domain\Model\Product;
use App\Product\Infrastructure\Doctrine\Entity\Product as ProductEntity;
use App\Product\Infrastructure\Transformer\ProductTransformer;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Doctrine\Entity\User as UserEntity;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(ProductTransformer::class)]
class ProductTransformerTest extends TestCase
{
    #[Test]
    public function fromDomain(): void
    {
        // Given
        $uuid = Uuid::v7()->toRfc4122();
        $userEntity = new UserEntity();

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findEntityById')
            ->with($uuid)
            ->willReturn($userEntity);

        $transformer = new ProductTransformer($userRepository);

        $product = Product::create(
            'Product name',
            'Description text',
            100.00,
            12,
            new UserId($uuid)
        );

        // When
        $productEntity = $transformer->fromDomain($product);

        // Then
        $this->assertEquals($product->getId(), $productEntity->getId());
        $this->assertEquals($product->getName(), $productEntity->getName());
        $this->assertEquals($product->getDescription(), $productEntity->getDescription());
        $this->assertEquals($product->getPrice(), $productEntity->getPrice());
        $this->assertEquals($product->getStockQuantity(), $productEntity->getStockQuantity());
        $this->assertEquals($product->getStatus(), $productEntity->getStatus());
        $this->assertEquals($userEntity, $productEntity->getUser());
    }

    #[Test]
    public function toDomain(): void
    {
        // Given
        $uuid = Uuid::v7()->toRfc4122();
        $userEntity = new UserEntity();
        $userEntity->setId($uuid);

        $productEntity = new ProductEntity();
        $productEntity->setId($uuid);
        $productEntity->setName('Product name');
        $productEntity->setDescription('Description text');
        $productEntity->setPrice(100.00);
        $productEntity->setStockQuantity(12);
        $productEntity->setStatus(StatusProduct::ACTIVE);
        $productEntity->setUser($userEntity);

        $userRepository = $this->createMock(UserRepository::class);

        $transformer = new ProductTransformer($userRepository);

        // When
        $product = $transformer->toDomain($productEntity);

        // Then
        $this->assertEquals($productEntity->getId(), $product->getId());
        $this->assertEquals($productEntity->getName(), $product->getName());
        $this->assertEquals($productEntity->getDescription(), $product->getDescription());
        $this->assertEquals($productEntity->getPrice(), $product->getPrice());
        $this->assertEquals($productEntity->getStockQuantity(), $product->getStockQuantity());
        $this->assertEquals($productEntity->getStatus(), $product->getStatus());
        $this->assertEquals($uuid, $product->getUserId()->getId());
    }

    #[Test]
    public function fromDomainUserNotFound(): void
    {
        $uuid = Uuid::v7()->toRfc4122();

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('User with ID '.$uuid.' not found.');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findEntityById')
            ->with($uuid)
            ->willReturn(null);

        $transformer = new ProductTransformer($userRepository);

        $product = Product::create(
            'Product name',
            'Description text',
            100.00,
            12,
            new UserId($uuid)
        );

        $transformer->fromDomain($product);
    }
}
