<?php

declare(strict_types=1);

namespace App\Contact\UI\Controller;

use App\Contact\Infrastructure\Http\Request\ContactMessageListRequest;
use App\Contact\Infrastructure\ReadModel\DbalContactMessageReader;
use App\Shared\Domain\Enum\Routes;
use App\User\Domain\Enum\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(attribute: Role::ROLE_ADMIN->value, message: 'Lack of a suitable role.')]
final readonly class AdminContactController
{
    public function __construct(
        private DbalContactMessageReader $reader,
    ) {
    }

    #[Route(path: Routes::ADMIN_CONTACT_PATH->value, name: Routes::ADMIN_CONTACT_NAME->value.'list', methods: [Request::METHOD_GET])]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ContactMessageListRequest $page = new ContactMessageListRequest()): JsonResponse
    {
        return new JsonResponse($this->reader->findPage($page->limit, $page->after));
    }
}
