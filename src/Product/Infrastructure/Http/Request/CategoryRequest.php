<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CategoryRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Use lowercase letters, digits and single hyphens.')]
    public string $slug;

    #[Assert\Length(max: 50)]
    public ?string $icon = null;

    #[Assert\Uuid]
    public ?string $parentId = null;
}
