<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\Providers\TvMazeProvider;

/**
 * Pipe for TVMaze API lookups.
 */
class TvMazePipe extends AbstractApiProviderPipe
{
    protected int $priority = 30;

    public function getName(): string
    {
        return 'TVMaze';
    }

    protected function siteColumn(): string
    {
        return 'tvmaze';
    }

    protected function createProvider(): AbstractTvProvider
    {
        return new TvMazeProvider;
    }
}
