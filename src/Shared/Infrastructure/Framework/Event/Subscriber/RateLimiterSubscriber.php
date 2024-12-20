<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Event\Subscriber;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class RateLimiterSubscriber implements EventSubscriberInterface
{
    private const string ROUTE = '_route';
    private const string ROUTE_NAME = 'api_';

    public function __construct(
        private ClockInterface $clock,
        private RateLimiterFactory $anonymousApiLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [RequestEvent::class => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (str_starts_with($request->get(self::ROUTE), self::ROUTE_NAME)) {
            $limiter = $this->anonymousApiLimiter->create($request->getClientIp());

            $limit = $limiter->consume();

            if (!$limit->isAccepted()) {
                $retryAfter = $limit->getRetryAfter()->getTimestamp() - $this->clock->now()->getTimestamp();

                $response = new JsonResponse(
                    data: ['error' => 'Too many requests. Try again in a few moments.'],
                    status: Response::HTTP_TOO_MANY_REQUESTS,
                    headers: ['Retry-After' => $retryAfter]
                );

                $event->setResponse($response);
            }
        }
    }
}
