<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Query;

/**
 * @template TResult of object
 */
interface Query
{
    /**
     * @return class-string<TResult>
     */
    public function resultType(): string;
}
