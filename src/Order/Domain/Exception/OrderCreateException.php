<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

class OrderCreateException extends \RuntimeException
{
    public function __construct(string $message = 'The order could not be created..')
    {
        parent::__construct($message);
    }
}
