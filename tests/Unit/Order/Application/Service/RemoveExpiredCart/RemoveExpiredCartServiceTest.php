<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Service\RemoveExpiredCart;

use App\Order\Application\Service\RemoveExpiredCart\RemoveExpiredCartService;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RemoveExpiredCartServiceTest extends TestCase
{
    private CartRepositoryInterface|MockObject $cartRepository;
    private ProductRepositoryInterface|MockObject $productRepository;
    private EntityManagerInterface|MockObject $entityManager;

    private RemoveExpiredCartService $service;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new RemoveExpiredCartService(
            $this->cartRepository,
            $this->productRepository,
            $this->entityManager
        );
    }

    public function testExpireCartsWithNoExpiredCarts(): void
    {
        $now = new \DateTimeImmutable('2024-01-01 10:00:00');

        $this->cartRepository->expects($this->once())
            ->method('findExpiredCarts')
            ->with($now)
            ->willReturn([]);

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->service->expireCarts($now);
    }
}
