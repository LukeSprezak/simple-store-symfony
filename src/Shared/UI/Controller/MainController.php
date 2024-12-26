<?php

declare(strict_types=1);

namespace App\Shared\UI\Controller;

use App\Shared\Domain\Enum\Routes;
use App\User\Domain\Enum\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(attribute: Role::ROLE_USER->value, message: 'Lack of a suitable role.')]
final class MainController
{
    #[Route(
        path: Routes::MAIN_PATH->value,
        name: Routes::MAIN_NAME->value,
        methods: [Request::METHOD_GET]
    )]
    public function main(): JsonResponse
    {
        return new JsonResponse(['status' => Response::HTTP_OK]);
    }
}
