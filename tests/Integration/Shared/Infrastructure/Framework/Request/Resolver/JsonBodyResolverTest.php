<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Framework\Request\Resolver;

use App\Product\Infrastructure\Http\Request\AddProductRequest;
use App\Shared\Infrastructure\Framework\Request\Resolver\JsonBodyResolver;
use App\Shared\Infrastructure\Framework\Validator\ValidationError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class JsonBodyResolverTest extends KernelTestCase
{
    #[Test]
    public function resolvesValidProductPayload(): void
    {
        $payloads = $this->resolve('{"name":"Product","description":"Description","price":12.50,"stockQuantity":3}');

        self::assertCount(1, $payloads);
        self::assertSame('Product', $payloads[0]->name);
        self::assertSame(12.5, $payloads[0]->price);
        self::assertSame(3, $payloads[0]->stockQuantity);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'malformed JSON' => ['{'];
        yield 'array instead of a name' => ['{"name":[]}'];
        yield 'string instead of quantity' => ['{"stockQuantity":"many"}'];
        yield 'fractional quantity' => ['{"stockQuantity":1.5}'];
        yield 'null instead of price' => ['{"price":null}'];
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function rejectsInvalidTypesAndPreservesTheCause(string $json): void
    {
        try {
            $this->resolve($json);
            self::fail('Invalid JSON payload should be rejected.');
        } catch (ValidationError $exception) {
            self::assertSame(['general' => 'VALIDATION.INVALID_PAYLOAD'], $exception->getErrors());
            self::assertNotNull($exception->getPrevious());
        }
    }

    /**
     * @return list<AddProductRequest>
     */
    private function resolve(string $json): array
    {
        $resolver = self::getContainer()->get(JsonBodyResolver::class);
        $request = Request::create('/api/product', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $json);
        $argument = new ArgumentMetadata('request', AddProductRequest::class, false, false, null);

        $payloads = iterator_to_array($resolver->resolve($request, $argument), false);
        $requests = [];
        foreach ($payloads as $payload) {
            self::assertInstanceOf(AddProductRequest::class, $payload);
            $requests[] = $payload;
        }

        return $requests;
    }
}
