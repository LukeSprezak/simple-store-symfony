<?php

declare(strict_types=1);

namespace App\Order\UI\Controller;

use App\Order\Application\Query\GetOrder\GetOrderQuery;
use App\Order\Application\Query\GetOrders\GetOrdersQuery;
use App\Order\Application\Query\GetOrderStatusHistory\GetOrderStatusHistoryQuery;
use App\Order\Infrastructure\Request\ChangeOrderStatusRequest;
use App\Order\Infrastructure\Request\OrderListRequest;
use App\Order\Infrastructure\Request\OrderStatusHistoryRequest;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\Enum\Routes;
use App\Shared\Infrastructure\Bus\Messenger\SyncCommandBus;
use App\User\Domain\Enum\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: Routes::ADMIN_ORDER_PATH->value, name: Routes::ADMIN_ORDER_NAME->value)]
#[IsGranted(attribute: Role::ROLE_ADMIN->value, message: 'Lack of a suitable role.')]
final readonly class AdminOrderController
{
    public function __construct(
        private QueryBus $queryBus,
        private SyncCommandBus $syncCommandBus,
    ) {
    }

    #[Route(path: '', name: 'list', methods: [Request::METHOD_GET])]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] OrderListRequest $page = new OrderListRequest()): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetOrdersQuery(null, $page->limit, $page->after)));
    }

    #[Route(path: '/{orderId}', name: 'get', requirements: ['orderId' => Requirement::UUID], methods: [Request::METHOD_GET])]
    public function get(string $orderId): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetOrderQuery($orderId)));
    }

    #[Route(path: '/{orderId}/status-history', name: 'status_history', requirements: ['orderId' => Requirement::UUID], methods: [Request::METHOD_GET])]
    public function statusHistory(string $orderId, #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] OrderStatusHistoryRequest $page = new OrderStatusHistoryRequest()): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetOrderStatusHistoryQuery($orderId, null, $page->limit, $page->after)));
    }

    /**
     * @param non-empty-string $orderId
     */
    #[Route(path: '/{orderId}/status', name: 'change_status', requirements: ['orderId' => Requirement::UUID], methods: [Request::METHOD_POST])]
    public function changeStatus(string $orderId, #[MapRequestPayload] ChangeOrderStatusRequest $request): JsonResponse
    {
        $this->syncCommandBus->dispatch($request->toCommand($orderId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
