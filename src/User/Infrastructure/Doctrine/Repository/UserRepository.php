<?php

namespace App\User\Infrastructure\Doctrine\Repository;

use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Infrastructure\Doctrine\Entity\User as UserEntity;
use App\User\Infrastructure\Transformer\UserTransformer;
use Doctrine\ORM\EntityManagerInterface;

readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserTransformer $userTransformer,
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
