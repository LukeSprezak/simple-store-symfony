<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger\Middleware;

use App\Shared\Infrastructure\Logging\CorrelationId;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Uuid;

final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CorrelationId $correlationId,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(CorrelationIdStamp::class);

        if (null === $envelope->last(ReceivedStamp::class)) {
            if (null === $stamp) {
                $envelope = $envelope->with(new CorrelationIdStamp($this->correlationId->get() ?? Uuid::v7()->toRfc4122()));
            }

            return $stack->next()->handle($envelope, $stack);
        }

        // Messages from before this middleware, the scheduler and the outbox carry no stamp.
        $previous = $this->correlationId->get();
        $this->correlationId->set($stamp->id ?? Uuid::v7()->toRfc4122());
        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->correlationId->set($previous);
        }
    }
}
