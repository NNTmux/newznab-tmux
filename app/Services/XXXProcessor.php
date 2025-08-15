<?php

namespace App\Services;

use App\Models\Settings;
use Blacklight\XXX;

class XXXProcessor
{
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupxxx') === 1) {
            (new XXX)->processXXXReleases();
        }
    }
}
