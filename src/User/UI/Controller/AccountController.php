<?php

declare(strict_types=1);

namespace App\User\UI\Controller;

use App\Shared\Domain\Enum\Routes;
use App\User\Domain\Enum\Role;
use App\User\Infrastructure\Doctrine\Entity\User;
use App\User\Infrastructure\Http\Request\ChangePasswordRequest;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: Routes::ACCOUNT_PATH->value, name: Routes::ACCOUNT_NAME->value)]
#[IsGranted(attribute: Role::ROLE_USER->value, message: 'Lack of a suitable role.')]
final readonly class AccountController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route(path: '', name: 'get', methods: [Request::METHOD_GET])]
    public function get(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ]);
    }

    // Changing the password revokes every earlier token, so the caller gets a fresh one.
    #[Route(path: '/password', name: 'change_password', methods: [Request::METHOD_POST])]
    public function changePassword(#[CurrentUser] User $user, #[MapRequestPayload] ChangePasswordRequest $request): JsonResponse
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->newPassword));
        $this->entityManager->flush();

        return new JsonResponse(['token' => $this->jwtManager->create($user)]);
    }
}
