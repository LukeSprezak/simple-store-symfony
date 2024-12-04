<?php

namespace App\User\Infrastructure\Transformer;

use App\User\Domain\Model\User;
use App\User\Infrastructure\Doctrine\Entity\User as UserEntity;

class UserTransformer
{
    public function fromDomain(User $user): UserEntity
    {
        $userEntity = new UserEntity();
        $userEntity->setId($user->getId());
        $userEntity->setUsername($user->getUsername());
        $userEntity->setEmail($user->getEmail());
        $userEntity->setRoles($user->getRoles());

        return $userEntity;
    }

    public function toDomain(UserEntity $userEntity): User
    {
        return User::fromPersistence(
            $userEntity->getId(),
            $userEntity->getUsername(),
            $userEntity->getEmail(),
            $userEntity->getRoles()
        );
    }
}
