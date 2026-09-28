<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\UuidValueObject;
use App\Shared\Domain\ValueObject\UuidValueObjectInterface;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UuidValueObject::class)]
final class UuidValueObjectTest extends TestCase
{
    #[Test]
    public function comparesIdsThroughTheInterfaceWithoutAccessingInternalProperties(): void
    {
        $userId = UserId::generate();
        $sameId = $this->createStub(UuidValueObjectInterface::class);
        $sameId->method('getId')->willReturn($userId->getId());
        $differentId = $this->createStub(UuidValueObjectInterface::class);
        $differentId->method('getId')->willReturn(UserId::generate()->getId());

        self::assertTrue($userId->equals($sameId));
        self::assertFalse($userId->equals($differentId));
        self::assertTrue($userId->equals(new UserId($userId->getId())));
    }
}
