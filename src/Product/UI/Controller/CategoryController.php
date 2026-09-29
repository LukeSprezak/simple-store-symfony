<?php

declare(strict_types=1);

namespace App\Product\UI\Controller;

use App\Product\Application\Query\GetCategories\GetCategoriesQuery;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\Enum\Routes;
use App\User\Domain\Enum\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(Role::ROLE_USER->value)]
final readonly class CategoryController
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    #[Route(path: Routes::CATEGORY_PATH->value, name: Routes::CATEGORY_NAME->value.'list', methods: [Request::METHOD_GET])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetCategoriesQuery()));
    }
}
