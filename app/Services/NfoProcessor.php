<?php

namespace App\Services;

use App\Models\Settings;
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
        if ((int) Settings::settingValue('lookupnfo') === 1) {
            $this->nfo->processNfoFiles(
                $nntp,
                $groupID,
                $guidChar,
                (bool) Settings::settingValue('lookupimdb'),
                (bool) Settings::settingValue('lookuptv')
            );
        }
    }
}
