<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\Infrastructure\Projection;

use App\Order\Domain\Event\CartCreated;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\ProductAddedToCart;
use App\Order\Infrastructure\Projection\CartActivityProjector;
use App\Shared\Application\Bus\Event\PublishedEvent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

final class CartActivityProjectorTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function invalidEvents(): iterable
    {
        yield 'unknown schema version' => [CartCreated::NAME, '{}', 2];
        yield 'malformed JSON' => [CartCreated::NAME, '{', 1];
        yield 'missing cart ID' => [CartCreated::NAME, '{}', 1];
        yield 'invalid cart ID' => [CartCreated::NAME, '{"cartId":"invalid"}', 1];
        yield 'invalid product quantity' => [ProductAddedToCart::NAME, json_encode(['cartId' => Uuid::v7()->toRfc4122(), 'productId' => Uuid::v7()->toRfc4122(), 'quantity' => -1], JSON_THROW_ON_ERROR), 1];
    }

    #[Test]
    #[DataProvider('invalidEvents')]
    public function rejectsInvalidEventsWithoutWritingPartialHistory(string $name, string $payload, int $version): void
    {
        self::bootKernel();
        $id = Uuid::v7()->toRfc4122();
        try {
            self::getContainer()->get(CartActivityProjector::class)(new PublishedEvent($id, $name, '2026-01-01T00:00:00+00:00', $payload, $version));
            self::fail('Invalid events must not be projected.');
        } catch (UnrecoverableMessageHandlingException) {
            self::assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM cart_activity WHERE event_id = ?', [$id]));
        }
    }

    #[Test]
    public function ignoresEventsBelongingToOtherProjections(): void
    {
        self::bootKernel();
        $id = Uuid::v7()->toRfc4122();
        self::getContainer()->get(CartActivityProjector::class)(new PublishedEvent($id, OrderPlaced::NAME, '2026-01-01T00:00:00+00:00', '{}'));

        self::assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM cart_activity WHERE event_id = ?', [$id]));
    }
}
