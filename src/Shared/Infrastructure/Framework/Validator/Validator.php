<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Validator;

use App\Shared\Infrastructure\Utils\Request\RequestInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class Validator
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function valid(RequestInterface $request): array
    {
        $violations = $this->validator->validate($request);

        if (0 === count($violations)) {
            return [];
        }

        return array_map(
            static fn (ConstraintViolationInterface $violation): string => (string) $violation->getMessage(),
            iterator_to_array($violations)
        );
    }
}
