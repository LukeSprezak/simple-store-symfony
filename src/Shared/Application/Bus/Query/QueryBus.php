<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Query;

interface QueryBus
{
    /**
     * @template TResult of object
     *
     * @param Query<TResult> $query
     *
     * @return TResult
     */
    public function ask(Query $query): object;
}
