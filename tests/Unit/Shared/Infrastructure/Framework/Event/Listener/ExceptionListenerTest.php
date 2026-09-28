<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Framework\Event\Listener;

use App\Product\Domain\Exception\ProductNotFoundException;
use App\Shared\Infrastructure\Framework\Event\Listener\ExceptionListener;
use App\Shared\Infrastructure\Framework\Validator\ValidationError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExceptionListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{bool}>
     */
    public static function debugModes(): iterable
    {
        yield 'debug disabled, including staging' => [false];
        yield 'debug enabled' => [true];
    }

    #[Test]
    #[DataProvider('debugModes')]
    public function exposesUnexpectedErrorDetailsOnlyInDebugMode(bool $debug): void
    {
        $response = $this->respond(new \RuntimeException('internal-database-detail'), $debug);

        self::assertSame(500, $response->getStatusCode());
        $body = $this->body($response);
        if ($debug) {
            self::assertSame('Unexpected error: internal-database-detail', $body['error']);
            self::assertArrayHasKey('trace', $body);
        } else {
            self::assertSame(['error' => 'An unexpected error occurred.'], $body);
        }
    }

    #[Test]
    #[DataProvider('debugModes')]
    public function keepsValidationErrorsWithoutExposingServerDetailsWhenDebugIsOff(bool $debug): void
    {
        $response = $this->respond(new ValidationError(['quantity' => 'Invalid quantity.']), $debug);

        self::assertSame(400, $response->getStatusCode());
        $errors = $this->body($response)['errors'];
        self::assertIsArray($errors);
        self::assertSame('Invalid quantity.', $errors['quantity']);
        if ($debug) {
            self::assertArrayHasKey('server', $errors);
        } else {
            self::assertSame(['quantity' => 'Invalid quantity.'], $errors);
        }
    }

    #[Test]
    public function preservesThe404StatusWithDebugDisabled(): void
    {
        $response = $this->respond(new ProductNotFoundException('missing-product'), false);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'An error occurred.'], $this->body($response));
    }

    private function respond(\Throwable $exception, bool $debug): Response
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $exception);
        new ExceptionListener(new NullLogger(), $debug)->onKernelException($event);
        $response = $event->getResponse();
        self::assertNotNull($response);

        return $response;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function body(Response $response): array
    {
        $content = $response->getContent();
        self::assertIsString($content);
        $body = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }
}
