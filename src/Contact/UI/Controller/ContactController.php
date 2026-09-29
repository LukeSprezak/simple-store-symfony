<?php

declare(strict_types=1);

namespace App\Contact\UI\Controller;

use App\Contact\Infrastructure\Doctrine\Entity\ContactMessage;
use App\Contact\Infrastructure\Http\Request\ContactMessageRequest;
use App\Shared\Domain\Enum\Routes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

// Public endpoint; the anonymous API rate limiter applies to it.
#[AsController]
final readonly class ContactController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: Routes::CONTACT_PATH->value, name: Routes::CONTACT_NAME->value.'submit', methods: [Request::METHOD_POST])]
    public function submit(#[MapRequestPayload] ContactMessageRequest $request): JsonResponse
    {
        $this->entityManager->persist(new ContactMessage(
            Uuid::v7()->toRfc4122(),
            $request->name,
            $request->email,
            $request->subject,
            $request->message,
            new \DateTimeImmutable(),
        ));
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_CREATED);
    }
}
