<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Infrastructure\Request;

use App\Order\Domain\Policy\CartLimits;
use App\Order\Infrastructure\Request\AddProductToCartRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class AddProductToCartRequestTest extends TestCase
{
    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function quantities(): iterable
    {
        yield 'minimum' => [1, true];
        yield 'maximum' => [CartLimits::MAX_QUANTITY_PER_PRODUCT, true];
        yield 'zero' => [0, false];
        yield 'negative' => [-1, false];
        yield 'above maximum' => [CartLimits::MAX_QUANTITY_PER_PRODUCT + 1, false];
        yield 'maximum integer' => [PHP_INT_MAX, false];
    }

    #[Test]
    #[DataProvider('quantities')]
    public function validatesRequestQuantity(int $quantity, bool $valid): void
    {
        $request = new AddProductToCartRequest();
        $request->productId = 'product';
        $request->quantity = $quantity;
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate($request);

        self::assertSame($valid, 0 === $violations->count());
        foreach ($violations as $violation) {
            self::assertSame('quantity', $violation->getPropertyPath());
        }
    }
}
