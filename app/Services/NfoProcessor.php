<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Configuration\ConfigurationProvider;
use App\Services\NNTP\NNTPService;

class NfoProcessor
{
    private NfoService $nfo;

    public function __construct(NfoService $nfo)
    {
        $this->nfo = $nfo;
    }

    /**
     * Process NFO files if enabled by settings.
     */
    public function process(NNTPService $nntp, string $groupID = '', string $guidChar = ''): void
    {
        if ((int) app(ConfigurationProvider::class)->postProcessing()->lookupNfo === 1) {
            $this->nfo->processNfoFiles(
                $nntp,
                $groupID,
                $guidChar,
                (bool) app(ConfigurationProvider::class)->metadata()->movieLookup->value,
                (bool) app(ConfigurationProvider::class)->metadata()->tvLookup->value
            );
        }
    }
}
