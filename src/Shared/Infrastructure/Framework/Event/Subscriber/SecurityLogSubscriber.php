<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Event\Subscriber;

use App\User\Infrastructure\Doctrine\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

#[WithMonologChannel('security')]
final readonly class SecurityLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    // Covers password logins, login throttling and rejected JWTs. The submitted identifier is not logged (PII).
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $this->logger->warning('Authentication failed.', [
            'firewall' => $event->getFirewallName(),
            'reason' => $event->getException()->getMessageKey(),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'ip' => $request->getClientIp(),
        ]);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $status = $event->getResponse()->getStatusCode();
        if (!$event->isMainRequest() || !in_array($status, [Response::HTTP_FORBIDDEN, Response::HTTP_TOO_MANY_REQUESTS], true)) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();
        $this->logger->warning(Response::HTTP_FORBIDDEN === $status ? 'Access denied.' : 'Rate limit exceeded.', [
            'status' => $status,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'ip' => $request->getClientIp(),
            'user_id' => $user instanceof User ? $user->getId() : null,
        ]);
    }
}
