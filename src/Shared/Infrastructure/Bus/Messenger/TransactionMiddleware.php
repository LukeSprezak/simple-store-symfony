<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class TransactionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->entityManager->beginTransaction();
        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();
            $this->entityManager->close();
            throw $exception;
        }

        return $envelope;
    }
}
