<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// Two levels: a top-level category (parentId null) and its subcategories; readers access this table through DBAL.
#[ORM\Entity]
#[ORM\Table(name: 'category')]
#[ORM\Index(name: 'idx_category_parent', columns: ['parent_id', 'position'])]
class Category
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID)]
        private string $id,
        #[ORM\Column(type: Types::GUID, nullable: true)]
        private ?string $parentId,
        #[ORM\Column(type: Types::STRING, length: 100)]
        private string $name,
        #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
        private string $slug,
        #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
        private ?string $icon,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
