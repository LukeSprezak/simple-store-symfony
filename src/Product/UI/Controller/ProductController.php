<?php

declare(strict_types=1);

namespace App\Product\UI\Controller;

use App\Product\Application\Command\RemoveProduct\RemoveProductCommand;
use App\Product\Infrastructure\Http\Request\AddProductRequest;
use App\Shared\Domain\Enum\Routes;
use App\Shared\Infrastructure\Bus\Messenger\AsyncCommandBus;
use App\Shared\Infrastructure\Framework\Request\Resolver\JsonBodyResolver;
use App\Shared\Infrastructure\Framework\Validator\Validator;
use App\Shared\Infrastructure\Utils\Response\ResponseProvider;
use App\User\Domain\Enum\Role;
use App\User\Infrastructure\Doctrine\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[AsController]
#[Route(path: Routes::PRODUCT_PATH->value, name: 'api_product_')]
#[IsGranted(attribute: Role::ROLE_SUPER_ADMIN->value, message: 'Lack of a suitable role.')]
final readonly class ProductController
{
    public function __construct(
        private AsyncCommandBus $asyncCommandBus,
        private Validator $validator,
    ) {
    }

    #[Route(
        path: Routes::ADD_PRODUCT_PATH->value,
        name: Routes::PRODUCT_NAME->value,
        methods: [Request::METHOD_POST]
    )]
    public function add(
        #[MapRequestPayload(resolver: JsonBodyResolver::class)] AddProductRequest $addProductRequest,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $error = $this->validator->valid($addProductRequest);
        if ([] !== $error) {
            return new JsonResponse(
                new ResponseProvider(Response::HTTP_BAD_REQUEST, 'Invalid.', null, $error),
                Response::HTTP_BAD_REQUEST
            );
        }

        $id = Uuid::v7()->toRfc4122();
        $this->asyncCommandBus->dispatch($addProductRequest->toCommand($id, $user->getId()));

        return new JsonResponse(['id' => $id], Response::HTTP_ACCEPTED);
    }

    #[Route(
        path: Routes::PRODUCT_REMOVE_PATH->value,
        name: Routes::PRODUCT_REMOVE_NAME->value,
        requirements: ['id' => Requirement::UUID],
        methods: [Request::METHOD_DELETE]
    )]
    public function remove(string $id): JsonResponse
    {
        $command = new RemoveProductCommand($id);
        $this->asyncCommandBus->dispatch($command);

        return new JsonResponse(['id' => $id], Response::HTTP_ACCEPTED);
    }
}
