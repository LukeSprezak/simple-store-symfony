<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Framework\Workflow;

use App\Order\Domain\Enum\StatusCart;
use App\Order\Domain\Model\Cart;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

class CartWorkflowListener
{
    #[AsGuardListener(workflow: 'cart_state')]
    public function onGuard(GuardEvent $event): void
    {
        $cart = $event->getSubject();
        if (!$cart instanceof Cart) {
            return;
        }

        if ($cart->getStatus()->value === StatusCart::CONVERTED_TO_ORDER->value) {
            $event->setBlocked(true);
            throw new AccessDeniedHttpException('Cannot modify a cart that has been converted to an order.');
        }
    }
}
