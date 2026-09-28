<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Validator;

class ValidationError extends \RuntimeException
{
    public const string GENERAL = 'general';

    /** @var array<string, string> */
    private array $errors;

    /**
     * @param array<string, string> $errors
     */
    public function __construct(array $errors, ?\Throwable $previous = null)
    {
        $this->errors = $errors;

        parent::__construct('Request is invalid.', 400, $previous);
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
