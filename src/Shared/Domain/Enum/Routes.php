<?php

declare(strict_types=1);

namespace App\Shared\Domain\Enum;

enum Routes: string
{
    case MAIN_PATH = '/';
    case MAIN_NAME = 'main';
}
