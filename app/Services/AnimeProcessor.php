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

    public function process(string $groupID = '', string $guidChar = ''): void
    {
        if ((int) Settings::settingValue('lookupanidb') !== 0) {
            (new AniDB)->processAnimeReleases($groupID, $guidChar);
        }
    }
}
