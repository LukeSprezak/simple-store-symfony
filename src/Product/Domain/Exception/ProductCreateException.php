<?php

declare(strict_types=1);

namespace App\Product\Domain\Exception;

class ProductCreateException extends \DomainException
{
    public function __construct(string $message = 'The product could not be created..')
    {
        parent::__construct($message);
    }
}
