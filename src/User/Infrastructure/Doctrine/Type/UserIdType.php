<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Doctrine\Type;

use App\User\Domain\ValueObject\UserId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class UserIdType extends Type
{
    public const string NAME = 'user_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    /**
     * @param string|null $value
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserId
    {
        return null === $value ? null : new UserId($value);
    }

    /**
     * @param UserId|null $value
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value?->getId();
    }
}
