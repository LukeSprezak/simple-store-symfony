<?php

declare(strict_types=1);

namespace App\Tests\Integration\Product\Application\Command\AddProduct;

use App\Product\Application\Command\AddProduct\AddProductCommand;
use App\Shared\Domain\ValueObject\Money;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Uid\Uuid;

class AddProductCommandHandlerTest extends KernelTestCase
{
    private MessageBusInterface $messageBus;
    private TransportInterface $transport;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->messageBus = self::getContainer()->get(MessageBusInterface::class);
        $this->transport = self::getContainer()->get('messenger.transport.async');
    }

    #[Test]
    public function shouldSendMessageToBrokerWhenProductWillBeAddedSuccessfully(): void
    {
        // Given
        $userId = Uuid::v7()->toRfc4122();
        $message = new AddProductCommand(
            Uuid::v7()->toRfc4122(),
            'Test Product',
            'Test Description',
            new Money(10000),
            12,
            new UserId($userId)
        );

        // When
        $this->messageBus->dispatch($message);

        // Then
        self::assertNotNull($message);
        self::assertEquals('Test Product', $message->name);
        self::assertEquals('Test Description', $message->description);
        self::assertSame(10000, $message->price->getAmount());
        self::assertEquals(12, $message->stockQuantity);
        self::assertEquals($userId, $message->userId->equals(new UserId($userId)));

        $messages = iterator_to_array($this->transport->get());
        self::assertCount(1, $messages, 'Expected one message in the transport.');
        $envelope = $messages[0];
        self::assertInstanceOf(AddProductCommand::class, $envelope->getMessage());
        $this->transport->ack($envelope);
    }
}
