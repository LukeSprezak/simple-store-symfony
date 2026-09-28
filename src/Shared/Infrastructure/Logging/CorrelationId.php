<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Contracts\Service\ResetInterface;

// Holds the ID of the request or message being handled and adds it to every log record.
#[AsMonologProcessor]
final class CorrelationId implements ProcessorInterface, ResetInterface
{
    private ?string $id = null;

    public function get(): ?string
    {
        return $this->id;
    }

    public function set(?string $id): void
    {
        $this->id = $id;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (null === $this->id) {
            return $record;
        }

        return $record->with(extra: [...$record->extra, 'correlation_id' => $this->id]);
    }

    public function reset(): void
    {
        $this->id = null;
    }
}
