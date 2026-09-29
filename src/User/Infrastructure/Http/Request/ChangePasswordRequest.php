<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Http\Request;

use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordRequest
{
    #[Assert\NotBlank]
    #[UserPassword(message: 'Current password is incorrect.')]
    public string $currentPassword;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, minMessage: 'Password needs to be at least 8 characters long.')]
    public string $newPassword;
}
