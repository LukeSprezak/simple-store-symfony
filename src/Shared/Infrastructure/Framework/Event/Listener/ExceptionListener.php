<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Event\Listener;

use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Infrastructure\Framework\Validator\ValidationError;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onKernelException')]
final readonly class ExceptionListener
{
    private bool $isProd;

    public function __construct(
        private LoggerInterface $logger,
        protected ParameterBagInterface $params,
    ) {
        $this->isProd = 'prod' === $params->get('kernel.environment');
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        [$statusCode, $errorData] = match (true) {
            $exception instanceof ValidationError => [
                Response::HTTP_BAD_REQUEST,
                $this->prepareValidationErrorResponse($exception),
            ],

            $exception instanceof BadRequestException => [
                Response::HTTP_BAD_REQUEST,
                $this->prepareSimpleErrorResponse($exception->getMessage()),
            ],

            $exception instanceof NotFoundException => [
                Response::HTTP_NOT_FOUND,
                $this->prepareSimpleErrorResponse($exception->getMessage()),
            ],

            $exception instanceof ConflictException,
            $exception instanceof OptimisticLockException => [
                Response::HTTP_CONFLICT,
                $this->prepareSimpleErrorResponse($exception->getMessage()),
            ],

            $exception instanceof HttpExceptionInterface => [
                $exception->getStatusCode(),
                $this->prepareSimpleErrorResponse($exception->getMessage()),
            ],

            default => [
                $this->handleUnexpectedException($exception),
                $this->prepareGeneralErrorResponse($exception),
            ],
        };

        $response = new JsonResponse($errorData, $statusCode);
        $event->setResponse($response);
    }

    private function prepareValidationErrorResponse(ValidationError $exception): array
    {
        $errors = $exception->getErrors();

        if (!$this->isProd) {
            $errors['server'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace(),
            ];
        }

        return ['errors' => $errors];
    }

    private function prepareSimpleErrorResponse(string $message): array
    {
        $error = $this->isProd ? 'An error occurred.' : $message;

        return ['error' => $error];
    }

    private function prepareGeneralErrorResponse(\Throwable $exception): array
    {
        if (!$this->isProd) {
            return [
                'error' => 'Unexpected error: '.$exception->getMessage(),
                'trace' => $exception->getTrace(),
            ];
        }

        return ['error' => 'An unexpected error occurred.'];
    }

    private function handleUnexpectedException(\Throwable $exception): int
    {
        $this->logger->error('Unexpected exception: '.$exception->getMessage(), [
            'exception' => $exception,
        ]);

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
