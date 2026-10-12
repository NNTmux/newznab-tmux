<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\Providers\TraktProvider;
use App\Support\MetadataSources;

/**
 * Pipe for Trakt API lookups.
 */
class TraktPipe extends AbstractApiProviderPipe
{
    protected int $priority = 50;

    public function getName(): string
    {
        return 'Trakt';
    }

    protected function siteColumn(): string
    {
        return 'trakt';
    }

    protected function createProvider(): AbstractTvProvider
    {
        return new TraktProvider;
    }

    protected function isAvailable(): bool
    {
        return MetadataSources::isAvailable(MetadataSources::TRAKT);
    }
}
