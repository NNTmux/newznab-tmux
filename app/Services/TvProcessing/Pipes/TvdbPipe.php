<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\Providers\TvdbProvider;
use App\Support\MetadataSources;

/**
 * Pipe for TVDB API lookups.
 */
class TvdbPipe extends AbstractApiProviderPipe
{
    protected int $priority = 20;

    public function getName(): string
    {
        return 'TVDB';
    }

    protected function siteColumn(): string
    {
        return 'tvdb';
    }

    protected function createProvider(): AbstractTvProvider
    {
        return new TvdbProvider;
    }

    protected function isAvailable(): bool
    {
        return MetadataSources::isAvailable(MetadataSources::TVDB);
    }
}
