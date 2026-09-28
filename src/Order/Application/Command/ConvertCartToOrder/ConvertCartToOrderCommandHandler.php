<?php

declare(strict_types=1);

namespace App\Order\Application\Command\ConvertCartToOrder;

use App\Order\Domain\Enum\StatusCartTransition;
use App\Order\Domain\Enum\StatusOrder;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Order\Domain\Exception\OrderCreateException;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Application\Bus\Command\Sync\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class ConvertCartToOrderCommandHandler implements CommandHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private OrderRepositoryInterface $orderRepository,
        #[Target('cart_state')]
        private WorkflowInterface $cartStateWorkflow,
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
                throw new AccessDeniedHttpException('Cannot convert an empty cart.');
            }

            $cart->applyTransition(StatusCartTransition::CONVERT->value, $this->cartStateWorkflow);

            $order = Order::create(Uuid::v7()->toRfc4122(), StatusOrder::CREATED->value, $cart->getOwnerId(), new \DateTimeImmutable());
            foreach ($cart->getActiveItems() as $cartItem) {
                $orderItem = $cartItem->toOrderItem();
                $order->addItem($orderItem);
            }

            $this->orderRepository->save($order);
            $this->cartRepository->save($cart);
        } catch (CartNotFoundException|AccessDeniedHttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new OrderCreateException('Unable to create order from cart.', 0, $exception);
        }
    }
}
