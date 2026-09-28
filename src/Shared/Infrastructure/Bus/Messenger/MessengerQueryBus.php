<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Application\Bus\Query\QueryBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerQueryBus implements QueryBus
{
    public function __construct(
        #[Autowire(service: 'query.bus')]
        private MessageBusInterface $queryBus,
    ) {
    }

    public function ask(Query $query): object
    {
        try {
            $envelope = $this->queryBus->dispatch($query);
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        $handled = $envelope->all(HandledStamp::class);
        if (1 !== count($handled)) {
            throw new \LogicException('A query must be handled synchronously by exactly one handler.');
        }

        $result = $handled[0]->getResult();
        $resultType = $query->resultType();
        if (!$result instanceof $resultType) {
            throw new \LogicException(sprintf('Query %s must return %s, got %s.', $query::class, $resultType, get_debug_type($result)));
        }

        return $result;
    }
}
