<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Validator;

class ValidationError extends \RuntimeException
{
    public const string GENERAL = 'general';

    private array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;

        parent::__construct('Request is invalid.', 400);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
