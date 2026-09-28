<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Event;

final readonly class PublishedEvent
{
    public function __construct(
        public string $id,
        public string $name,
        public string $recordedAt,
        public string $payload,
        public int $schemaVersion = 1,
    ) {
    }
}
