<?php

namespace App\Services;

use App\Models\Settings;
use Blacklight\Games;

class GamesProcessor
{
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupgames') !== 0) {
            (new Games)->processGamesReleases();
        }
    }
}
