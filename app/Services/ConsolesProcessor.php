<?php

namespace App\Services;

use App\Models\Settings;
use Blacklight\Console;

class ConsolesProcessor
{
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupgames') !== 0) {
            (new Console)->processConsoleReleases();
        }
    }
}
