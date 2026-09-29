<?php

declare(strict_types=1);

namespace App\Product\Application\ReadModel;

interface ProductReader
{
    public function findActive(string $id): ?ProductView;

    public function findActivePage(int $limit, ?string $after, ?string $categorySlug): ProductPage;
}
