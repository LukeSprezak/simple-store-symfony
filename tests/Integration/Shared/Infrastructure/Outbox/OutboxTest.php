<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Outbox;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Event\CartCreated;
use App\Order\Domain\Model\Cart;
use App\Order\Infrastructure\Repository\CartRepository;
use App\Shared\Application\Bus\Event\PublishedEvent;
use App\Shared\Infrastructure\Outbox\OutboxPublisher;
use App\Shared\UI\Cli\PublishOutboxCommand;
use App\User\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection as AmqpConnection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Uid\Uuid;

final class OutboxTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();
        foreach (['domain_event_outbox', 'cart', 'cart_activity'] as $table) {
            $schema = $this->connection->fetchAssociative('SHOW CREATE TABLE '.$table);
            self::assertIsArray($schema);
            self::assertIsString($schema['Create Table']);
            $this->connection->executeStatement(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $schema['Create Table']));
        }
        $this->clock = new MockClock('2030-01-01T00:00:00+00:00');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $this->entityManager->clear();
            $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS domain_event_outbox, cart, cart_activity');
        }
        parent::tearDown();
    }

    #[Test]
    public function persistsTheAggregateAndItsEventTogetherAndDoesNotPublishInline(): void
    {
        $cart = $this->cart();
        self::getContainer()->get(CartRepository::class)->save($cart);
        self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM domain_event_outbox'));
        $this->entityManager->flush();

        self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM cart'));
        $row = $this->connection->fetchAssociative('SELECT * FROM domain_event_outbox');
        self::assertIsArray($row);
        self::assertSame(CartCreated::NAME, $row['event_name']);
        self::assertSame(0, $row['attempts']);
        self::assertNull($row['published_at']);
        self::assertIsString($row['payload']);
        self::assertSame(['cartId' => $cart->getId(), 'ownerId' => $cart->getOwnerId()->getId()], json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR));

        self::getContainer()->get(CartRepository::class)->save($cart);
        $this->entityManager->flush();
        self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM domain_event_outbox'));
    }

    #[Test]
    public function rollbackRemovesBothTheAggregateAndTheOutboxEntry(): void
    {
        $this->connection->beginTransaction();
        self::getContainer()->get(CartRepository::class)->save($this->cart());
        $this->entityManager->flush();
        self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM domain_event_outbox'));
        $this->connection->rollBack();
        $this->entityManager->clear();

        self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM cart'));
        self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM domain_event_outbox'));
    }

    #[Test]
    public function aFailedAggregateInsertCannotLeaveAnOrphanedEvent(): void
    {
        $cart = $this->cart();
        $this->connection->insert('cart', ['id' => $cart->getId(), 'owner_id' => $cart->getOwnerId()->getId(), 'status' => 'active', 'created_at' => '2026-01-01 00:00:00', 'expires_at' => '2026-01-02 00:00:00']);
        self::getContainer()->get(CartRepository::class)->save($cart);
        try {
            $this->entityManager->flush();
            self::fail('The duplicate aggregate must fail.');
        } catch (UniqueConstraintViolationException) {
            self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM domain_event_outbox'));
        }
    }

    #[Test]
    public function publishesWithAStableIdAndMarksOnlyConfirmedMessages(): void
    {
        $id = $this->seed();
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->willReturnCallback(static function (Envelope $envelope) use ($id): Envelope {
            $message = $envelope->getMessage();
            self::assertInstanceOf(PublishedEvent::class, $message);
            self::assertSame($id, $message->id);
            self::assertSame(CartCreated::NAME, $message->name);
            self::assertSame(1, $message->schemaVersion);
            self::assertSame('event.bus', $envelope->last(BusNameStamp::class)?->getBusName());

            return $envelope;
        });
        $publisher = $this->publisher($sender);
        self::assertTrue($publisher->publishNext());
        self::assertNull($publisher->publishNext());
        self::assertSame('2030-01-01 00:00:00', $this->connection->fetchOne('SELECT published_at FROM domain_event_outbox WHERE id = ?', [$id]));
    }

    #[Test]
    public function aLostConfirmationRetainsTheEventAndRetriesWithTheSameIdAfterBackoff(): void
    {
        $id = $this->seed();
        $calls = 0;
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::exactly(2))->method('send')->willReturnCallback(static function (Envelope $envelope) use (&$calls, $id): Envelope {
            $message = $envelope->getMessage();
            self::assertInstanceOf(PublishedEvent::class, $message);
            self::assertSame($id, $message->id);
            if (1 === ++$calls) {
                throw new \RuntimeException('Simulated lost confirmation; confidential connection details');
            }

            return $envelope;
        });
        $publisher = $this->publisher($sender);
        self::assertFalse($publisher->publishNext());
        self::assertNull($this->connection->fetchOne('SELECT published_at FROM domain_event_outbox WHERE id = ?', [$id]));
        self::assertSame(\RuntimeException::class, $this->connection->fetchOne('SELECT last_error FROM domain_event_outbox WHERE id = ?', [$id]));
        self::assertNull($publisher->publishNext());
        $this->clock->sleep(2);
        self::assertTrue($publisher->publishNext());
        self::assertSame(2, $this->connection->fetchOne('SELECT attempts FROM domain_event_outbox WHERE id = ?', [$id]));
    }

    #[Test]
    public function refusesToPublishInsideAnUncommittedBusinessTransaction(): void
    {
        $this->connection->beginTransaction();
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');
        $this->expectException(\LogicException::class);

        $this->publisher($sender)->publishNext();
    }

    #[Test]
    public function theCliBoundsEachBatchAndRejectsInvalidLimits(): void
    {
        $this->seed();
        $this->seed();
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->willReturnArgument(0);
        $tester = new CommandTester(new PublishOutboxCommand($this->publisher($sender)));

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '1']));
        self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM domain_event_outbox WHERE published_at IS NULL'));
        self::assertSame(Command::INVALID, $tester->execute(['--limit' => '0']));
        self::assertSame(Command::INVALID, $tester->execute(['--limit' => '1001']));
        self::assertSame(Command::INVALID, $tester->execute(['--limit' => 'abc']));
    }

    private function seed(): string
    {
        $id = Uuid::v7()->toRfc4122();
        $this->connection->insert('domain_event_outbox', ['id' => $id, 'event_name' => CartCreated::NAME, 'payload' => json_encode(['cartId' => Uuid::v7()->toRfc4122()], JSON_THROW_ON_ERROR), 'recorded_at' => '2026-01-01 00:00:00', 'available_at' => '2026-01-01 00:00:00', 'schema_version' => 1, 'attempts' => 0]);

        return $id;
    }

    #[Test]
    public function publishesThroughRabbitMqAndProjectsRedeliveryOnlyOnce(): void
    {
        $dsn = getenv('MESSENGER_RABBITMQ_TRANSPORT_DSN');
        self::assertIsString($dsn);
        $queue = 'outbox_test_'.str_replace('-', '', Uuid::v7()->toRfc4122());
        $amqp = AmqpConnection::fromDsn($dsn, [
            'confirm_timeout' => 5,
            'exchange' => ['name' => $queue, 'type' => 'direct', 'default_publish_routing_key' => $queue],
            'queues' => [$queue => ['binding_keys' => [$queue]]],
        ]);
        $transport = new AmqpTransport($amqp, self::getContainer()->get('messenger.transport.symfony_serializer'));
        try {
            $id = $this->seed();
            self::assertTrue($this->publisher($transport)->publishNext());
            $first = true;
            for ($index = 0; $index < 2; ++$index) {
                $messages = iterator_to_array($transport->get());
                self::assertCount(1, $messages);
                $received = reset($messages);
                $event = $received->getMessage();
                self::assertInstanceOf(PublishedEvent::class, $event);
                self::assertSame($id, $event->id);
                self::assertSame('event.bus', $received->last(BusNameStamp::class)?->getBusName());
                self::getContainer()->get('event.bus')->dispatch($received->with(new ReceivedStamp('events')));
                $transport->ack($received);
                if ($first) {
                    $transport->send(new Envelope($event, [new BusNameStamp('event.bus')]));
                    $first = false;
                }
            }
            self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM cart_activity WHERE event_id = ?', [$id]));
        } finally {
            $amqp->queue($queue)->delete();
            $amqp->exchange()->delete($queue);
            $transport->close();
        }
    }

    private function publisher(SenderInterface $sender): OutboxPublisher
    {
        return new OutboxPublisher($this->connection, $sender, $this->clock, new NullLogger());
    }

    private function cart(): Cart
    {
        return Cart::create(Uuid::v7()->toRfc4122(), StatusCart::ACTIVE, UserId::generate());
    }
}
