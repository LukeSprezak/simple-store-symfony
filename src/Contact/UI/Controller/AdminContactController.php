<?php

declare(strict_types=1);

namespace App\Contact\UI\Controller;

use App\Contact\Infrastructure\Doctrine\Entity\ContactMessage;
use App\Contact\Infrastructure\Http\Request\ContactMessageListRequest;
use App\Contact\Infrastructure\ReadModel\DbalContactMessageReader;
use App\Shared\Domain\Enum\Routes;
use App\User\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(attribute: Role::ROLE_ADMIN->value, message: 'Lack of a suitable role.')]
final readonly class AdminContactController
{
    public function __construct(
        private DbalContactMessageReader $reader,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: Routes::ADMIN_CONTACT_PATH->value, name: Routes::ADMIN_CONTACT_NAME->value.'list', methods: [Request::METHOD_GET])]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ContactMessageListRequest $page = new ContactMessageListRequest()): JsonResponse
    {
        return new JsonResponse($this->reader->findPage($page->limit, $page->after));
    }

    #[Route(path: Routes::ADMIN_CONTACT_PATH->value.'/{id}/read', name: Routes::ADMIN_CONTACT_NAME->value.'mark_read', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_POST])]
    public function markRead(string $id): JsonResponse
    {
        $this->find($id)->markRead();
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: Routes::ADMIN_CONTACT_PATH->value.'/{id}/read', name: Routes::ADMIN_CONTACT_NAME->value.'mark_unread', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_DELETE])]
    public function markUnread(string $id): JsonResponse
    {
        $this->find($id)->markUnread();
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function find(string $id): ContactMessage
    {
        return $this->entityManager->find(ContactMessage::class, $id)
            ?? throw new NotFoundHttpException(sprintf('Contact message "%s" does not exist.', $id));
    }
}
