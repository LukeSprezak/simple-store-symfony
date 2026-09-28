<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class MoneyType extends Type
{
    public const string NAME = 'money';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    /**
     * @param int|string|null $value
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Money
    {
        return null === $value ? null : new Money((int) $value);
    }

    /**
     * @param Money|null $value
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return $value?->getAmount();
    }
}
