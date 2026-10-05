<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Configuration\ConfigurationProvider;

class MusicProcessor
{
    /** @phpstan-ignore property.onlyWritten */
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(string $groupID = '', string $guidChar = ''): void
    {
        if ((int) app(ConfigurationProvider::class)->metadata()->musicLookup->value !== 0) {
            (new MusicService)->processMusicReleases(false, $groupID, $guidChar);
        }
    }
}
