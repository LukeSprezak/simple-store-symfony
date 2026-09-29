<?php

declare(strict_types=1);

namespace App\Shared\Domain\Enum;

enum Routes: string
{
    // Main page
    case MAIN_PATH = '/api';
    case MAIN_NAME = 'api_main';
    // Product
    case PRODUCT_PATH = '/api/product';
    case PRODUCT_NAME = 'api_product_';
    case ADD_PRODUCT_PATH = '/add';
    case ADD_PRODUCT_NAME = 'add';
    case PRODUCT_REMOVE_PATH = '/remove/{id}';
    case PRODUCT_REMOVE_NAME = 'remove';
    // Cart
    case CART_PATH = '/api/cart';
    case CART_NAME = 'api_cart_';
    case ADD_PRODUCT_TO_CART_PATH = '/add-product/{cartId}';
    case ADD_PRODUCT_TO_CART_NAME = 'add_product';
    case CONVERT_PRODUCT_TO_ORDER_PATH = '/{cartId}/convert';
    case CONVERT_PRODUCT_TO_ORDER_NAME = 'convert_to_order';
    case REMOVE_PRODUCT_FROM_CART_PATH = '/remove-product';
    case REMOVE_PRODUCT_FROM_CART_NAME = 'remove_product';
    // Order
    case ORDER_PATH = '/api/order';
    case ORDER_NAME = 'api_order_';
    case ADMIN_ORDER_PATH = '/api/admin/order';
    case ADMIN_ORDER_NAME = 'api_admin_order_';
    // Account
    case ACCOUNT_PATH = '/api/me';
    case ACCOUNT_NAME = 'api_account_';
    case ADMIN_ACCOUNT_PATH = '/api/admin/me';
    case ADMIN_ACCOUNT_NAME = 'api_admin_account_';
}
