<?php

declare(strict_types=1);

namespace App\Shared\Domain\Enum;

enum Routes: string
{
    case MAIN_PATH = '/';
    case MAIN_NAME = 'main';
    case PRODUCT_PATH = '/api/product';
    case PRODUCT_NAME = 'api_product_';
    case ADD_PRODUCT_PATH = '/add';
    case ADD_PRODUCT_NAME = 'add';
    case PRODUCT_REMOVE_PATH = '/remove/{id}';
    case PRODUCT_REMOVE_NAME = 'remove';
}
