<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Infrastructure\Transformer;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Model\Cart as CartDomain;
use App\Order\Domain\Model\ProductSnapshot;
use App\Order\Infrastructure\Doctrine\Entity\Cart as CartEntity;
use App\Order\Infrastructure\Doctrine\Entity\CartItem as CartItemEntity;
use App\Order\Infrastructure\Transformer\CartTransformer;
use App\Product\Domain\Model\Product as ProductEntity;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(CartTransformer::class)]
class CartTransformerTest extends TestCase
{
    #[Test]
    public function shouldTransformToDomain(): void
    {
        // Given
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $productRepository = $this->createMock(EntityRepository::class);
        $entityManager->method('getRepository')
            ->with(ProductEntity::class)
            ->willReturn($productRepository);

        $transformer = new CartTransformer($entityManager);

        $cartEntity = $this->createMock(CartEntity::class);
        $cartId = Uuid::v7()->toRfc4122();
        $cartEntity->method('getId')->willReturn($cartId);
        $cartEntity->method('getStatus')->willReturn(StatusCart::ACTIVE);
        $cartEntity->method('getOwnerId')->willReturn(Uuid::v7()->toRfc4122());
        $createdAt = new \DateTimeImmutable('2026-09-28 10:00:00');
        $expiresAt = new \DateTimeImmutable('2026-09-29 10:00:00');
        $cartEntity->method('getCreatedAt')->willReturn($createdAt);
        $cartEntity->method('getExpiresAt')->willReturn($expiresAt);

        $productEntity1 = $this->createMock(ProductEntity::class);
        $productId1 = Uuid::v7()->toRfc4122();
        $productEntity1->method('getId')->willReturn($productId1);
        $productEntity1->method('getName')->willReturn('Product 1');
        $productEntity1->method('getPrice')->willReturn(new Money(10000));

        $cartItemEntity1 = $this->createMock(CartItemEntity::class);
        $cartItemId1 = Uuid::v7()->toRfc4122();
        $cartItemEntity1->method('getId')->willReturn($cartItemId1);
        $cartItemEntity1->method('getProduct')->willReturn($productEntity1);
        $cartItemEntity1->method('getQuantity')->willReturn(3);

        $productEntity2 = $this->createMock(ProductEntity::class);
        $productId2 = Uuid::v7()->toRfc4122();
        $productEntity2->method('getId')->willReturn($productId2);
        $productEntity2->method('getName')->willReturn('Product 2');
        $productEntity2->method('getPrice')->willReturn(new Money(8000));

        $cartItemEntity2 = $this->createMock(CartItemEntity::class);
        $cartItemId2 = Uuid::v7()->toRfc4122();
        $cartItemEntity2->method('getId')->willReturn($cartItemId2);
        $cartItemEntity2->method('getProduct')->willReturn($productEntity2);
        $cartItemEntity2->method('getQuantity')->willReturn(5);

        $cartItems = new ArrayCollection([$cartItemEntity1, $cartItemEntity2]);
        $cartEntity->method('getItems')->willReturn($cartItems);

        // When
        $cartDomain = $transformer->toDomain($cartEntity);

        // Then
        self::assertInstanceOf(CartDomain::class, $cartDomain);
        self::assertSame($cartId, $cartDomain->getId());
        self::assertSame(StatusCart::ACTIVE, $cartDomain->getStatus());
        self::assertSame($createdAt, $cartDomain->getCreatedAt());
        self::assertSame($expiresAt, $cartDomain->getExpiresAt());

        $items = $cartDomain->getItems();
        self::assertCount(2, $items, 'CartDomain should have 2 items.');

        $itemDomain1 = $items->first();
        self::assertSame($cartItemId1, $itemDomain1->getId());
        self::assertSame(3, $itemDomain1->getQuantity());

        $productDomain1 = $itemDomain1->getProduct();
        self::assertInstanceOf(ProductSnapshot::class, $productDomain1);
        self::assertSame($productId1, $productDomain1->getId());
        self::assertSame('Product 1', $productDomain1->getName());
        self::assertSame(10000, $productDomain1->getPrice()->getAmount());

        $itemDomain2 = $items->last();
        self::assertSame($cartItemId2, $itemDomain2->getId());
        self::assertSame(5, $itemDomain2->getQuantity());

        $productDomain2 = $itemDomain2->getProduct();
        self::assertInstanceOf(ProductSnapshot::class, $productDomain2);
        self::assertSame($productId2, $productDomain2->getId());
        self::assertSame('Product 2', $productDomain2->getName());
        self::assertSame(8000, $productDomain2->getPrice()->getAmount());
    }
}
