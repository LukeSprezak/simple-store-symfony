<?php

declare(strict_types=1);

namespace App\Order\UI\Controller;

use App\Order\Application\Query\GetOrders\GetOrdersQuery;
use App\Order\Application\Query\GetOrderStatusHistory\GetOrderStatusHistoryQuery;
use App\Order\Infrastructure\Request\ChangeOrderStatusRequest;
use App\Order\Infrastructure\Request\OrderListRequest;
use App\Order\Infrastructure\Request\OrderStatusHistoryRequest;
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
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: Routes::ORDER_PATH->value, name: Routes::ORDER_NAME->value)]
#[IsGranted(attribute: Role::ROLE_USER->value, message: 'Lack of a suitable role.')]
final readonly class OrderController
{
    public function __construct(
        private QueryBus $queryBus,
        private SyncCommandBus $syncCommandBus,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route(path: '', name: 'list', methods: [Request::METHOD_GET])]
    public function list(#[CurrentUser] User $user, #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] OrderListRequest $page = new OrderListRequest()): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetOrdersQuery($this->ownerFilter($user), $page->limit, $page->after)));
    }

    #[Route(path: '/{orderId}/status-history', name: 'status_history', requirements: ['orderId' => Requirement::UUID], methods: [Request::METHOD_GET])]
    public function statusHistory(string $orderId, #[CurrentUser] User $user, #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] OrderStatusHistoryRequest $page = new OrderStatusHistoryRequest()): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetOrderStatusHistoryQuery($orderId, $this->ownerFilter($user), $page->limit, $page->after)));
    }

    /**
     * @param non-empty-string $orderId
     */
    #[Route(path: '/{orderId}/status', name: 'change_status', requirements: ['orderId' => Requirement::UUID], methods: [Request::METHOD_POST])]
    #[IsGranted(attribute: Role::ROLE_ADMIN->value, message: 'Lack of a suitable role.')]
    public function changeStatus(string $orderId, #[MapRequestPayload] ChangeOrderStatusRequest $request): JsonResponse
    {
        $this->syncCommandBus->dispatch($request->toCommand($orderId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    // Admins manage every order; other users only see their own.
    private function ownerFilter(User $user): ?UserId
    {
        return $this->authorizationChecker->isGranted(Role::ROLE_ADMIN->value) ? null : new UserId($user->getId());
    }
}
