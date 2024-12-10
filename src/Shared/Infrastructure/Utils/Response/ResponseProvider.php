<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Utils\Response;

use Symfony\Component\HttpFoundation\Response;

final readonly class ResponseProvider
{
    public function __construct(
        public int $status = Response::HTTP_OK,
        public ?string $message = null,
        public ?array $data = null,
        public ?array $error = null,
    ) {
    }
}
