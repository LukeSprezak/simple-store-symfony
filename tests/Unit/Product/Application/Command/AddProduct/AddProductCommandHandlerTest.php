<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Application\Command\AddProduct;

use App\Product\Application\Command\AddProduct\AddProductCommand;
use App\Product\Application\Command\AddProduct\AddProductCommandHandler;
use App\Product\Domain\Model\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(AddProductCommandHandler::class)]
class AddProductCommandHandlerTest extends TestCase
{
    private ProductRepositoryInterface|MockObject $productRepository;
    private AddProductCommandHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->handler = new AddProductCommandHandler(
            $this->productRepository
        );
    }

    #[Test]
    public function shouldHandleAddProductCommand(): void
    {
        // Given
        $userId = Uuid::v7()->toRfc4122();
        $expectedName = 'Test Product';
        $expectedDescription = 'Test Description';
        $expectedPrice = 100.00;
        $expectedStockQuantity = 12;

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $product) use ($userId, $expectedName, $expectedDescription, $expectedPrice, $expectedStockQuantity) {
                return $product->getName() === $expectedName
                    && $product->getDescription() === $expectedDescription
                    && $product->getPrice() === $expectedPrice
                    && $product->getStockQuantity() === $expectedStockQuantity
                    && $product->getUserId()->getId() === $userId;
            }));

        $command = new AddProductCommand(
            Uuid::v7()->toRfc4122(),
            $expectedName,
            $expectedDescription,
            $expectedPrice,
            $expectedStockQuantity,
            new UserId($userId)
        );

        // When
        $this->handler->__invoke($command);
    }

    #[Test]
    public function shouldHandleAddProductCommandGenericException(): void
    {
        // given
        $userId = Uuid::v7()->toRfc4122();
        $this->productRepository->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('Database error'));

        $command = new AddProductCommand(
            Uuid::v7()->toRfc4122(),
            'Product name',
            'Description text',
            100.00,
            12,
            new UserId($userId)
        );

        // then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An unexpected error occurred while adding the product.');

        // when
        $this->handler->__invoke($command);
    }
}
