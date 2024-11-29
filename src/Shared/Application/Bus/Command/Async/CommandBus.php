<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Command\Async;

interface CommandBus
{
    public function dispatch(Command $command): void;
}
