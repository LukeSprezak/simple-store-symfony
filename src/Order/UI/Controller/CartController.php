<?php

declare(strict_types=1);

namespace App\Order\UI\Controller;

use App\Order\Application\Command\ConvertCartToOrder\ConvertCartToOrderCommand;
use App\Order\Application\Query\GetCart\GetCartQuery;
use App\Order\Infrastructure\Request\AddProductToCartRequest;
use App\Order\Infrastructure\Request\RemoveProductFromCartRequest;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\Enum\Routes;
use App\Shared\Infrastructure\Bus\Messenger\SyncCommandBus;
use App\User\Domain\Enum\Role;
use App\User\Domain\ValueObject\UserId;
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
#[Route(path: Routes::CART_PATH->value, name: Routes::CART_NAME->value)]
#[IsGranted(attribute: Role::ROLE_USER->value, message: 'Lack of a suitable role.')]
final readonly class CartController
{
    public function __construct(
        private SyncCommandBus $syncCommandBus,
        private QueryBus $queryBus,
    ) {
    }

    #[Route(path: '/{cartId}', name: 'get', requirements: ['cartId' => Requirement::UUID], methods: [Request::METHOD_GET])]
    public function get(string $cartId, #[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetCartQuery($cartId, new UserId($user->getId()))));
    }

    #[Route(
        path: Routes::ADD_PRODUCT_TO_CART_PATH->value,
        name: Routes::ADD_PRODUCT_TO_CART_NAME->value,
        requirements: ['cartId' => Requirement::UUID],
        defaults: ['cartId' => null],
        methods: [Request::METHOD_POST]
    )]
    public function addProductToCart(
        #[MapRequestPayload] AddProductToCartRequest $addProductToCartRequest,
        #[CurrentUser] User $user,
        ?string $cartId,
    ): JsonResponse {
        $createCart = null === $cartId;
        $cartId ??= Uuid::v4()->toRfc4122();

        $this->syncCommandBus->dispatch($addProductToCartRequest->toCommand($cartId, $createCart, $user->getId()));

        return new JsonResponse(['cartId' => $cartId], Response::HTTP_OK);
    }

    #[Route(
        path: Routes::CONVERT_PRODUCT_TO_ORDER_PATH->value,
        name: Routes::CONVERT_PRODUCT_TO_ORDER_NAME->value,
        requirements: ['cartId' => Requirement::UUID],
        methods: [Request::METHOD_POST]
    )]
    public function convertToOrder(string $cartId, #[CurrentUser] User $user): JsonResponse
    {
        $this->syncCommandBus->dispatch(new ConvertCartToOrderCommand($cartId, new UserId($user->getId())));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: Routes::REMOVE_PRODUCT_FROM_CART_PATH->value,
        name: Routes::REMOVE_PRODUCT_FROM_CART_NAME->value,
        methods: [Request::METHOD_DELETE]
    )]
    public function removeProductFromCart(
        #[MapRequestPayload] RemoveProductFromCartRequest $removeProductFromCartRequest,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $this->syncCommandBus->dispatch($removeProductFromCartRequest->toCommand($user->getId()));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
