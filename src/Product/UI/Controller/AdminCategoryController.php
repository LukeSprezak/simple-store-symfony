<?php

declare(strict_types=1);

namespace App\Product\UI\Controller;

use App\Product\Application\Query\GetCategories\GetCategoriesQuery;
use App\Product\Infrastructure\Doctrine\Entity\Category;
use App\Product\Infrastructure\Http\Request\CategoryRequest;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\Enum\Routes;
use App\User\Domain\Enum\Role;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[AsController]
#[IsGranted(attribute: Role::ROLE_ADMIN->value, message: 'Lack of a suitable role.')]
final readonly class AdminCategoryController
{
    public function __construct(
        private QueryBus $queryBus,
        private EntityManagerInterface $entityManager,
        private Connection $connection,
    ) {
    }

    #[Route(path: Routes::ADMIN_CATEGORY_PATH->value, name: Routes::ADMIN_CATEGORY_NAME->value.'list', methods: [Request::METHOD_GET])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetCategoriesQuery()));
    }

    #[Route(path: Routes::ADMIN_CATEGORY_PATH->value, name: Routes::ADMIN_CATEGORY_NAME->value.'create', methods: [Request::METHOD_POST])]
    public function create(#[MapRequestPayload] CategoryRequest $request): JsonResponse
    {
        $id = Uuid::v7()->toRfc4122();
        $this->assertValid($request, null);

        /** @var int|string $position */
        $position = $this->connection->fetchOne('SELECT COALESCE(MAX(position), -1) + 1 FROM category');
        $this->entityManager->persist(new Category($id, $request->parentId, $request->name, $request->slug, $this->icon($request), (int) $position));
        $this->entityManager->flush();

        return new JsonResponse(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route(path: Routes::ADMIN_CATEGORY_PATH->value.'/{id}', name: Routes::ADMIN_CATEGORY_NAME->value.'update', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_PUT])]
    public function update(string $id, #[MapRequestPayload] CategoryRequest $request): JsonResponse
    {
        $category = $this->find($id);
        $this->assertValid($request, $category);

        $category->update($request->parentId, $request->name, $request->slug, $this->icon($request));
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: Routes::ADMIN_CATEGORY_PATH->value.'/{id}', name: Routes::ADMIN_CATEGORY_NAME->value.'delete', requirements: ['id' => Requirement::UUID], methods: [Request::METHOD_DELETE])]
    public function delete(string $id): JsonResponse
    {
        $category = $this->find($id);
        if (false !== $this->connection->fetchOne('SELECT 1 FROM category WHERE parent_id = :id LIMIT 1', ['id' => $id])) {
            throw new ConflictHttpException('Move or delete its subcategories first.');
        }
        // Products keep their category, so removing one they reference would leave them orphaned.
        if (false !== $this->connection->fetchOne('SELECT 1 FROM product WHERE category_id = :id LIMIT 1', ['id' => $id])) {
            throw new ConflictHttpException('The category still has products.');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    // Keeps the tree two levels deep and slugs unique.
    private function assertValid(CategoryRequest $request, ?Category $category): void
    {
        $existing = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $request->slug]);
        if (null !== $existing && $existing !== $category) {
            throw new ConflictHttpException(sprintf('The slug "%s" is already used.', $request->slug));
        }

        if (null === $request->parentId) {
            return;
        }

        $parent = $this->entityManager->find(Category::class, $request->parentId);
        if (null === $parent || null !== $parent->getParentId() || $parent === $category) {
            throw new UnprocessableEntityHttpException('The parent must be another top-level category.');
        }
        if (null !== $category && false !== $this->connection->fetchOne('SELECT 1 FROM category WHERE parent_id = :id LIMIT 1', ['id' => $category->getId()])) {
            throw new UnprocessableEntityHttpException('A category with subcategories must stay top-level.');
        }
    }

    // Only top-level categories show an icon in the shop menu.
    private function icon(CategoryRequest $request): ?string
    {
        return null === $request->parentId && '' !== $request->icon ? $request->icon : null;
    }

    private function find(string $id): Category
    {
        return $this->entityManager->find(Category::class, $id)
            ?? throw new NotFoundHttpException(sprintf('Category "%s" does not exist.', $id));
    }
}
