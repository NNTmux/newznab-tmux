<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\Providers\TmdbProvider;
use App\Support\MetadataSources;

/**
 * Pipe for TMDB API lookups.
 */
class TmdbPipe extends AbstractApiProviderPipe
{
    protected int $priority = 40;

    public function getName(): string
    {
        return 'TMDB';
    }

    protected function siteColumn(): string
    {
        return 'tmdb';
    }

    protected function createProvider(): AbstractTvProvider
    {
        return new TmdbProvider;
    }

    protected function isAvailable(): bool
    {
        return MetadataSources::isAvailable(MetadataSources::TMDB);
    }
}
