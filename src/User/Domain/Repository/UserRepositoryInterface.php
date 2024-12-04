<?php

declare(strict_types=1);

namespace App\User\Domain\Repository;

use App\User\Domain\Model\User;
use App\User\Infrastructure\Doctrine\Entity\User as UserEntity;

interface UserRepositoryInterface
{
    public function find(string $id): ?User;
    public function findEntityById(string $id): ?UserEntity;
}
