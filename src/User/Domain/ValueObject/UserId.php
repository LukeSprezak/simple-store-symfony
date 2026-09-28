<?php

declare(strict_types=1);

namespace App\User\Domain\ValueObject;

use App\Shared\Domain\ValueObject\UuidValueObject;
use App\Shared\Domain\ValueObject\UuidValueObjectInterface;

class UserId extends UuidValueObject implements UuidValueObjectInterface
{
}
