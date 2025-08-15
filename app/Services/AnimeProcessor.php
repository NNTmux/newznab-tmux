<?php

namespace App\Services;

use App\Models\Settings;
use Blacklight\processing\post\AniDB;

class AnimeProcessor
{
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupanidb') !== 0) {
            (new AniDB())->processAnimeReleases();
        }
    }
}

