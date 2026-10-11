<?php

declare(strict_types=1);

namespace App\Services\Tmux;

interface CollectionReconciliationBarrier
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $work
     * @return TResult
     */
    public function withReconciliationBarrier(int $delayHours, callable $work): mixed;
}
