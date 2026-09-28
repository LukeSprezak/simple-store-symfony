<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Enum\StatusOrderTransition;
use App\Order\Domain\Exception\OrderTransitionNotAllowedException;
use App\Order\Domain\Model\Order;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Order::class)]
class OrderTest extends TestCase
{
    /**
     * @return iterable<string, array{StatusOrder, StatusOrderTransition}>
     */
    public static function allowedTransitions(): iterable
    {
        foreach (StatusOrderTransition::cases() as $transition) {
            foreach ($transition->allowedFrom() as $from) {
                yield $transition->value.' from '.$from->value => [$from, $transition];
            }
        }
    }

    #[Test]
    #[DataProvider('allowedTransitions')]
    public function shouldApplyAllowedTransition(StatusOrder $from, StatusOrderTransition $transition): void
    {
        // Given
        $order = $this->createOrder($from);

        // When
        $order->apply($transition);

        // Then
        self::assertSame($transition->target()->value, $order->getStatus());
    }

    #[Test]
    public function shouldGoThroughReturnPath(): void
    {
        // Given
        $order = $this->createOrder(StatusOrder::DELIVERED);

        // When
        $order->apply(StatusOrderTransition::REQUEST_RETURN);
        $order->apply(StatusOrderTransition::RETRIEVED);
        $order->apply(StatusOrderTransition::COMPLETE_RETURN);

        // Then
        self::assertSame(StatusOrder::RETURNED->value, $order->getStatus());
    }

    #[Test]
    public function shouldRejectNotAllowedTransition(): void
    {
        // Given
        $order = $this->createOrder(StatusOrder::CREATED);

        // Then
        $this->expectException(OrderTransitionNotAllowedException::class);

        // When
        $order->apply(StatusOrderTransition::SHIP);
    }

    private function createOrder(StatusOrder $status): Order
    {
        return Order::create(Uuid::v7()->toRfc4122(), $status->value, UserId::generate(), new \DateTimeImmutable());
    }
}
