<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Product\Application\Query\GetProduct\GetProductQuery;
use App\Product\Application\ReadModel\ProductView;
use App\Product\Domain\Exception\ProductNotFoundException;
use App\Shared\Infrastructure\Bus\Messenger\MessengerQueryBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class MessengerQueryBusTest extends TestCase
{
    #[Test]
    public function returnsTheSingleHandlersTypedResult(): void
    {
        $view = new ProductView('product-id', 'Product', 'Description', 1234, 10);
        $bus = $this->bus([static fn (GetProductQuery $query): ProductView => $view]);

        self::assertSame($view, $bus->ask(new GetProductQuery('product-id')));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidHandlerCounts(): iterable
    {
        yield 'no synchronous result' => [0];
        yield 'multiple handlers' => [2];
    }

    #[Test]
    #[DataProvider('invalidHandlerCounts')]
    public function requiresExactlyOneSynchronousHandler(int $count): void
    {
        $handlers = [];
        for ($index = 0; $index < $count; ++$index) {
            $handlers[] = static fn (GetProductQuery $query): ProductView => new ProductView($query->productId, 'Product', 'Description', 1234, 10);
        }
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('exactly one handler');

        $this->bus($handlers)->ask(new GetProductQuery('product-id'));
    }

    #[Test]
    public function rejectsAResultOfTheWrongType(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must return '.ProductView::class);

        $this->bus([static fn (GetProductQuery $query): \stdClass => new \stdClass()])->ask(new GetProductQuery('product-id'));
    }

    #[Test]
    public function preservesTheOriginalHandlerException(): void
    {
        $exception = new ProductNotFoundException('missing-product');
        $this->expectExceptionObject($exception);

        $this->bus([static fn (GetProductQuery $query): never => throw $exception])->ask(new GetProductQuery('missing-product'));
    }

    /**
     * @param list<callable(GetProductQuery): object> $handlers
     */
    private function bus(array $handlers): MessengerQueryBus
    {
        $descriptors = [];
        foreach ($handlers as $index => $handler) {
            $descriptors[] = new HandlerDescriptor($handler, ['alias' => 'handler'.$index]);
        }

        return new MessengerQueryBus(new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([GetProductQuery::class => $descriptors]), allowNoHandlers: true),
        ]));
    }
}
