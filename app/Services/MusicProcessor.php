<?php

namespace App\Services;

use App\Models\Settings;

class MusicProcessor
{
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupmusic') !== 0) {
            (new MusicService)->processMusicReleases();
        }
    }
}
