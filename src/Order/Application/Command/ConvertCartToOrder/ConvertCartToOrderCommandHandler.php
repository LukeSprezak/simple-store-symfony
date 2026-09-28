<?php

declare(strict_types=1);

namespace App\Order\Application\Command\ConvertCartToOrder;

use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Order\Domain\Exception\EmptyCartException;
use App\Order\Domain\Exception\OrderCreateException;
use App\Order\Domain\Model\CartItem;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderItem;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Application\Bus\Command\Sync\CommandHandler;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Uid\Uuid;

final readonly class ConvertCartToOrderCommandHandler implements CommandHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function __invoke(ConvertCartToOrderCommand $command): void
    {
        try {
            $cart = $this->cartRepository->find($command->cartId);

            if (!$cart || !$cart->isOwnedBy($command->userId)) {
                throw new CartNotFoundException($command->cartId);
            }

            if ($cart->isEmpty()) {
                throw new EmptyCartException($cart->getId());
            }

            $cart->convert();

            $order = Order::create(
                Uuid::v7()->toRfc4122(),
                StatusOrder::CREATED->value,
                $cart->getOwnerId(),
                new \DateTimeImmutable(),
                $cart->getActiveItems()->map(static fn (CartItem $cartItem): OrderItem => $cartItem->toOrderItem())->getValues(),
            );

            $this->orderRepository->save($order);
            $this->cartRepository->save($cart);
        } catch (NotFoundException|ConflictException|OptimisticLockException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new OrderCreateException('Unable to create order from cart.', 0, $exception);
        }
    }
}
