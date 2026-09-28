<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundException;

final class CartOwnerNotFoundException extends \RuntimeException implements NotFoundException
{
    public function __construct()
    {
        parent::__construct('The cart owner does not exist.');
    }
}
