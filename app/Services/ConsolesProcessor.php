<?php

namespace App\Services;

use App\Models\Settings;

class ConsolesProcessor
{
    /** @phpstan-ignore property.onlyWritten */
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupgames') !== 0) {
            (new ConsoleService)->processConsoleReleases();
        }
    }
}
