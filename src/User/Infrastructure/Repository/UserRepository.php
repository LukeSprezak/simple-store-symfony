<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Repository;

use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Infrastructure\Doctrine\Entity\User as UserEntity;
use App\User\Infrastructure\Transformer\UserTransformer;
use Doctrine\ORM\EntityManagerInterface;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserTransformer $userTransformer,
    ) {
    }

    public function find(string $id): ?User
    {
        $userEntity = $this->entityManager->getRepository(UserEntity::class)->find($id);

        if (!$userEntity) {
            return null;
        }

        return $this->userTransformer->toDomain($userEntity);
    }

    public function findEntityById(string $id): ?UserEntity
    {
        return $this->entityManager->getRepository(UserEntity::class)->find($id);
    }
}
