<?php

declare(strict_types=1);

namespace App\Contact\Infrastructure\ReadModel;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalContactMessageReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function countUnread(): int
    {
        /** @var int|string $count */
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM contact_message WHERE read_at IS NULL');

        return (int) $count;
    }

    /**
     * Newest first: message IDs are UUIDv7, so descending ID order is creation order.
     *
     * @return array{items: list<array{id: string, name: string, email: string, subject: string, message: string, createdAt: string, readAt: ?string}>, nextCursor: ?string}
     */
    public function findPage(int $limit, ?string $after): array
    {
        /** @var list<array{id: string, name: string, email: string, subject: string, message: string, created_at: string, read_at: ?string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, email, subject, message, created_at, read_at FROM contact_message'.(null === $after ? '' : ' WHERE id < :after').' ORDER BY id DESC LIMIT :limit',
            ['limit' => $limit + 1] + (null === $after ? [] : ['after' => $after]),
            ['limit' => ParameterType::INTEGER]
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $items = array_map(static fn (array $row): array => [
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'subject' => $row['subject'],
            'message' => $row['message'],
            'createdAt' => new \DateTimeImmutable($row['created_at'])->format(DATE_ATOM),
            'readAt' => null === $row['read_at'] ? null : new \DateTimeImmutable($row['read_at'])->format(DATE_ATOM),
        ], $rows);

        $lastItem = end($items);

        return ['items' => $items, 'nextCursor' => $hasMore && false !== $lastItem ? $lastItem['id'] : null];
    }
}
