<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Infrastructure\Bus\Messenger\TransactionMiddleware;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class TransactionMiddlewareTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private TransactionMiddleware $middleware;
    private MockObject $nextMiddleware;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->middleware = new TransactionMiddleware($this->entityManager);
        $this->nextMiddleware = $this->createMock(MiddlewareInterface::class);
    }

    #[Test]
    public function shouldSuccessfullyApproveTransaction(): void
    {
        // Given
        $envelope = new Envelope(new \stdClass());

        $this->entityManager->expects($this->once())
            ->method('beginTransaction');

        $this->nextMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willReturn($envelope);

        $this->entityManager->expects($this->once())
            ->method('commit');

        $stackMock = $this->createMock(StackInterface::class);
        $stackMock->expects($this->once())
            ->method('next')
            ->willReturn($this->nextMiddleware);

        // When
        $result = $this->middleware->handle($envelope, $stackMock);

        // Then
        self::assertSame($envelope, $result);
    }

    #[Test]
    public function shouldRollbackTransactionOnException(): void
    {
        // Given
        $envelope = new Envelope(new \stdClass());

        $this->entityManager->expects($this->once())
            ->method('beginTransaction');

        $this->nextMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willThrowException(new \Exception('Test Exception'));


        $stackMock = $this->createMock(StackInterface::class);
        $stackMock->expects($this->once())
            ->method('next')
            ->willReturn($this->nextMiddleware);

        $this->entityManager->expects($this->once())
            ->method('rollback');

        $this->entityManager->expects($this->once())
            ->method('close');

        // Then
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test Exception');

        // When
        $this->middleware->handle($envelope, $stackMock);
    }
}
