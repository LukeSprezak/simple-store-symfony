<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Event\Subscriber;

use App\Shared\Infrastructure\Logging\CorrelationId;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

final readonly class CorrelationIdSubscriber implements EventSubscriberInterface
{
    private const string HEADER = 'X-Request-Id';

    public function __construct(
        private CorrelationId $correlationId,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Before the router and firewall, so their log records carry the ID.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // A client-supplied ID is accepted only as a UUID, so it cannot inject content into logs.
        $id = $event->getRequest()->headers->get(self::HEADER);
        $this->correlationId->set(null !== $id && Uuid::isValid($id) ? $id : Uuid::v7()->toRfc4122());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || null === $this->correlationId->get()) {
            return;
        }

        $event->getResponse()->headers->set(self::HEADER, $this->correlationId->get());
    }
}
