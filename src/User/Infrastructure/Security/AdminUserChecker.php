<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\User\Domain\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

// Staff panel login: the regular account checks plus a staff role requirement.
final readonly class AdminUserChecker implements UserCheckerInterface
{
    public function __construct(
        private UserChecker $userChecker,
        private RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        $this->userChecker->checkPreAuth($user);
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!in_array(Role::ROLE_ADMIN->value, $this->roleHierarchy->getReachableRoleNames($user->getRoles()), true)) {
            throw new CustomUserMessageAccountStatusException('Staff access only.');
        }
    }
}
