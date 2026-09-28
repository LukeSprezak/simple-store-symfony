<?php

declare(strict_types=1);

namespace App\User\Domain\Repository;

use App\User\Domain\Model\User;

interface UserRepositoryInterface
{
    public function find(string $id): ?User;
}
