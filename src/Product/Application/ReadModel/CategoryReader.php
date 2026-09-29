<?php

declare(strict_types=1);

namespace App\Product\Application\ReadModel;

interface CategoryReader
{
    public function findTree(): CategoryTree;
}
