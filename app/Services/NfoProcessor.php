<?php

namespace App\Services;

use App\Models\Settings;
use Blacklight\Nfo;
use Blacklight\NNTP;

class NfoProcessor
{
    private Nfo $nfo;

    public function __construct(Nfo $nfo, bool $echooutput)
    {
        $this->nfo = $nfo;
        // echooutput kept for signature parity
    }

    /**
     * Process NFO files if enabled by settings.
     */
    public function process(NNTP $nntp, string $groupID = '', string $guidChar = ''): void
    {
        if ((int) Settings::settingValue('lookupnfo') === 1) {
            $this->nfo->processNfoFiles(
                $nntp,
                $groupID,
                $guidChar,
                (int) Settings::settingValue('lookupimdb'),
                (int) Settings::settingValue('lookuptv')
            );
        }
    }
}
