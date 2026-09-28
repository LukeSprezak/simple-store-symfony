<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Outbox;

use App\Order\Domain\Event\CartCreated;
use App\Shared\Application\Bus\Event\PublishedEvent;
use App\Shared\Infrastructure\Outbox\OutboxPublisher;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @phpstan-import-type Params from DriverManager
 */
final class OutboxConcurrencyTest extends KernelTestCase
{
    private Connection $first;
    private Connection $second;
    /** @var list<string> */
    private array $ids = [];

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var Params $parameters */
        $parameters = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->getParams();
        $this->first = DriverManager::getConnection($parameters);
        $this->second = DriverManager::getConnection($parameters);
        if (0 !== $this->first->fetchOne('SELECT COUNT(*) FROM domain_event_outbox WHERE published_at IS NULL')) {
            self::markTestSkipped('An existing test-database backlog must not be published by this test.');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->first, $this->second)) {
            foreach ([$this->first, $this->second] as $connection) {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            }
            foreach ($this->ids as $id) {
                $this->first->delete('domain_event_outbox', ['id' => $id]);
            }
            $this->first->close();
            $this->second->close();
        }
        parent::tearDown();
    }

    #[Test]
    public function concurrentPublishersSkipTheLockedMessageAndDoNotSendItTwice(): void
    {
        $firstId = $this->seed();
        $secondId = $this->seed();
        $secondSender = $this->createMock(SenderInterface::class);
        $secondSender->expects(self::once())->method('send')->willReturnCallback(static function (Envelope $envelope) use ($secondId): Envelope {
            $message = $envelope->getMessage();
            self::assertInstanceOf(PublishedEvent::class, $message);
            self::assertSame($secondId, $message->id);

            return $envelope;
        });
        $secondPublisher = $this->publisher($this->second, $secondSender);
        $firstSender = $this->createMock(SenderInterface::class);
        $firstSender->expects(self::once())->method('send')->willReturnCallback(static function (Envelope $envelope) use ($firstId, $secondPublisher): Envelope {
            $message = $envelope->getMessage();
            self::assertInstanceOf(PublishedEvent::class, $message);
            self::assertSame($firstId, $message->id);
            self::assertTrue($secondPublisher->publishNext());
            self::assertNull($secondPublisher->publishNext());

            return $envelope;
        });

        self::assertTrue($this->publisher($this->first, $firstSender)->publishNext());
        self::assertSame(2, $this->first->fetchOne('SELECT COUNT(*) FROM domain_event_outbox WHERE id IN (?, ?) AND published_at IS NOT NULL', [$firstId, $secondId]));
    }

    #[Test]
    public function anUncommittedEventIsNotVisibleToThePublisher(): void
    {
        $this->first->beginTransaction();
        $this->seed();
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->willReturnArgument(0);
        $publisher = $this->publisher($this->second, $sender);
        self::assertNull($publisher->publishNext());
        $this->first->commit();
        self::assertTrue($publisher->publishNext());
    }

    private function seed(): string
    {
        $id = Uuid::v7()->toRfc4122();
        $this->ids[] = $id;
        $this->first->insert('domain_event_outbox', ['id' => $id, 'event_name' => CartCreated::NAME, 'payload' => '{}', 'recorded_at' => '2026-01-01 00:00:00', 'available_at' => '2026-01-01 00:00:00', 'schema_version' => 1, 'attempts' => 0]);

        return $id;
    }

    private function publisher(Connection $connection, SenderInterface $sender): OutboxPublisher
    {
        return new OutboxPublisher($connection, $sender, new MockClock('2030-01-01T00:00:00+00:00'), new NullLogger());
    }
}
