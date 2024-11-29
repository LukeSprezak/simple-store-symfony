<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Command\Sync;

interface CommandBus
{
    public function dispatch(Command $command): void;
}
