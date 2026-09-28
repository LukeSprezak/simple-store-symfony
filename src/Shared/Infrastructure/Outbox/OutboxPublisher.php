<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Bus\Event\PublishedEvent;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

final readonly class OutboxPublisher
{
    public function __construct(
        private Connection $connection,
        #[Autowire(service: 'messenger.transport.events')]
        private SenderInterface $sender,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    // null: nothing due; true: broker confirmed; false: retained for retry.
    public function publishNext(): ?bool
    {
        if ($this->connection->isTransactionActive()) {
            throw new \LogicException('The outbox publisher must run outside a business transaction.');
        }

        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $this->connection->beginTransaction();
        try {
            /** @var array{id: string, event_name: string, payload: string, recorded_at: string, schema_version: int|string, attempts: int|string}|false $row */
            $row = $this->connection->fetchAssociative(
                'SELECT id, event_name, payload, recorded_at, schema_version, attempts FROM domain_event_outbox
                 WHERE published_at IS NULL AND available_at <= :now ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED',
                ['now' => $now->format('Y-m-d H:i:s')]
            );
            if (false === $row) {
                $this->connection->commit();

                return null;
            }

            try {
                $message = new PublishedEvent($row['id'], $row['event_name'], new \DateTimeImmutable($row['recorded_at'], new \DateTimeZone('UTC'))->format(DATE_ATOM), $row['payload'], (int) $row['schema_version']);
                $this->sender->send(new Envelope($message, [new BusNameStamp('event.bus')]));
            } catch (\Throwable $exception) {
                $delay = min(300, 2 ** min(9, (int) $row['attempts'] + 1));
                $this->connection->executeStatement(
                    'UPDATE domain_event_outbox SET attempts = attempts + 1, available_at = :retry, last_error = :error WHERE id = :id',
                    ['id' => $row['id'], 'retry' => $now->modify('+'.$delay.' seconds')->format('Y-m-d H:i:s'), 'error' => $exception::class]
                );
                $this->connection->commit();
                $this->logger->warning('Outbox publication failed; event retained for retry.', ['eventId' => $row['id'], 'errorClass' => $exception::class]);

                return false;
            }

            $this->connection->executeStatement('UPDATE domain_event_outbox SET published_at = :now, attempts = attempts + 1, last_error = NULL WHERE id = :id', ['now' => $now->format('Y-m-d H:i:s'), 'id' => $row['id']]);
            $this->connection->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
