<?php

declare(strict_types=1);

namespace App\Order\UI\Controller;

use App\Order\Application\Query\GetOrderStatusHistory\GetOrderStatusHistoryQuery;
use App\Order\Infrastructure\Request\OrderStatusHistoryRequest;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\Enum\Routes;
use App\User\Domain\Enum\Role;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Doctrine\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: Routes::ORDER_PATH->value, name: Routes::ORDER_NAME->value)]
#[IsGranted(attribute: Role::ROLE_USER->value, message: 'Lack of a suitable role.')]
final readonly class OrderController
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    #[Route(path: '/{orderId}/status-history', name: 'status_history', requirements: ['orderId' => Requirement::UUID], methods: [Request::METHOD_GET])]
    public function statusHistory(string $orderId, #[CurrentUser] User $user, #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] OrderStatusHistoryRequest $page = new OrderStatusHistoryRequest()): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetOrderStatusHistoryQuery($orderId, new UserId($user->getId()), $page->limit, $page->after)));
    }
}
