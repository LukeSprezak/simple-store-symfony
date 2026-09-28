<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product\Application\Command\RemoveProduct;

use App\Product\Application\Command\RemoveProduct\RemoveProductCommand;
use App\Product\Application\Command\RemoveProduct\RemoveProductCommandHandler;
use App\Product\Domain\Exception\ProductRemoveException;
use App\Product\Domain\Model\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(RemoveProductCommandHandler::class)]
class RemoveProductCommandHandlerTest extends TestCase
{
    private ProductRepositoryInterface|MockObject $productRepository;
    private RemoveProductCommandHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->handler = new RemoveProductCommandHandler(
            $this->productRepository
        );
    }

    #[Test]
    public function shouldHandleRemoveProductCommand(): void
    {
        // Given
        $productId = Uuid::v7()->toRfc4122();
        $product = $this->createMock(Product::class);
        $product->expects($this->once())
            ->method('deactivate');

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with($this->equalTo($productId))
            ->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($this->equalTo($product));

        // When
        $command = new RemoveProductCommand($productId);
        $this->handler->__invoke($command);
    }

    #[Test]
    public function shouldHandleRemoveProductCommandGenericException(): void
    {
        // Given
        $productId = Uuid::v7()->toRfc4122();

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with($this->equalTo($productId))
            ->willReturn($this->createMock(Product::class));

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Product::class))
            ->willThrowException(new \Exception('Database error'));

        $command = new RemoveProductCommand($productId);

        // Then
        $this->expectException(ProductRemoveException::class);
        $this->expectExceptionMessage('Failed to remove the product.');

        // When
        $this->handler->__invoke($command);
    }
}
