<?php

declare(strict_types=1);

namespace App\Order\UI\Controller;

use App\Order\Application\Command\ConvertCartToOrder\ConvertCartToOrderCommand;
use App\Order\Domain\Exception\CartNotFoundException;
use App\Order\Infrastructure\Repository\CartRepository;
use App\Order\Infrastructure\Request\AddProductToCartRequest;
use App\Shared\Domain\Enum\Routes;
use App\Shared\Infrastructure\Bus\Messenger\SyncCommandBus;
use App\User\Domain\Enum\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[AsController]
#[Route(path: Routes::CART_PATH->value, name: Routes::CART_NAME->value)]
#[IsGranted(attribute: Role::ROLE_USER->value, message: 'Lack of a suitable role.')]
final readonly class CartController
{
    public function __construct(
        private SyncCommandBus $syncCommandBus,
        private CartRepository $cartRepository,
    ) {
    }

    #[Route(
        path: Routes::ADD_PRODUCT_TO_CART_PATH->value,
        name: Routes::ADD_PRODUCT_TO_CART_NAME->value,
        defaults: ['cartId' => null],
        methods: [Request::METHOD_POST]
    )]
    public function addProductToCart(
        #[MapRequestPayload] AddProductToCartRequest $addProductToCartRequest,
        ?string $cartId,
    ): JsonResponse {
        if (null === $cartId) {
            $cartId = Uuid::v4()->toRfc4122();
        }

        $addProductToCartRequest->cartId = $cartId;

        $this->syncCommandBus->dispatch($addProductToCartRequest->toCommand());

        return new JsonResponse(['cartId' => $cartId], Response::HTTP_OK);
    }

    #[Route(
        path: Routes::CONVERT_PRODUCT_TO_ORDER_PATH->value,
        name: Routes::CONVERT_PRODUCT_TO_ORDER_NAME->value,
        methods: [Request::METHOD_POST]
    )]
    public function convertToOrder(string $cartId): JsonResponse
    {
        $cartDomain = $this->cartRepository->find($cartId);
        if (!$cartDomain) {
            throw new CartNotFoundException($cartId);
        }

        $command = new ConvertCartToOrderCommand($cartId);
        $this->syncCommandBus->dispatch($command);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
