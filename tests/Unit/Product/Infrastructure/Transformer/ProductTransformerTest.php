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
            Uuid::v7()->toRfc4122(),
            'Product name',
            'Description text',
            100.00,
            12,
            new UserId($uuid)
        );

        // When
        $productEntity = $transformer->fromDomain($product);

        // Then
        self::assertEquals($product->getId(), $productEntity->getId());
        self::assertEquals($product->getName(), $productEntity->getName());
        self::assertEquals($product->getDescription(), $productEntity->getDescription());
        self::assertEquals($product->getPrice(), $productEntity->getPrice());
        self::assertEquals($product->getStockQuantity(), $productEntity->getStockQuantity());
        self::assertEquals($product->getStatus(), $productEntity->getStatus());
        self::assertEquals($userEntity, $productEntity->getUser());
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
        self::assertEquals($productEntity->getId(), $product->getId());
        self::assertEquals($productEntity->getName(), $product->getName());
        self::assertEquals($productEntity->getDescription(), $product->getDescription());
        self::assertEquals($productEntity->getPrice(), $product->getPrice());
        self::assertEquals($productEntity->getStockQuantity(), $product->getStockQuantity());
        self::assertEquals($productEntity->getStatus(), $product->getStatus());
        self::assertEquals($uuid, $product->getUserId()->getId());
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
            Uuid::v7()->toRfc4122(),
            'Product name',
            'Description text',
            100.00,
            12,
            new UserId($uuid)
        );

        $transformer->fromDomain($product);
    }
}
