<?php

declare(strict_types=1);

namespace App\Contact\Infrastructure\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactMessageRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 254)]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public string $subject;

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 5000)]
    public string $message;
}
