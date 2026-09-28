<?php

declare(strict_types=1);

namespace App\Product\UI\Controller;

use App\Product\Application\Query\GetProduct\GetProductQuery;
use App\Product\Application\Query\GetProducts\GetProductsQuery;
use App\Product\Infrastructure\Http\Request\ProductListRequest;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\Enum\Routes;
use App\User\Domain\Enum\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(Role::ROLE_USER->value)]
final readonly class ProductReadController
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    #[Route(path: Routes::PRODUCT_PATH->value, name: 'api_product_list', methods: [Request::METHOD_GET])]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ProductListRequest $page = new ProductListRequest()): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetProductsQuery($page->limit, $page->after)));
    }

    #[Route(path: Routes::PRODUCT_PATH->value.'/{id}', name: 'api_product_get', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_GET])]
    public function get(string $id): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetProductQuery($id)));
    }
}
