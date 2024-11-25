<?php

declare(strict_types=1);

namespace App\Shared\UI\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class MainController
{
    #[Route(
        path:'/',
        name: 'main',
        methods: [Request::METHOD_GET],
    )]
    public function main(): JsonResponse
    {
        return new JsonResponse(['status' => Response::HTTP_OK]);
    }
}
