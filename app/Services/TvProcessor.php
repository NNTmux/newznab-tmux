<?php

namespace App\Services;

use App\Models\Settings;
use Blacklight\processing\tv\TMDB;
use Blacklight\processing\tv\TraktTv;
use Blacklight\processing\tv\TVDB;
use Blacklight\processing\tv\TVMaze;

class TvProcessor
{
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    /**
     * Process all TV related releases across supported providers.
     *
     * @param  string  $groupID
     * @param  string  $guidChar
     * @param  int|string|null  $processTV  0/1/2 or '' to read from settings
     */
    public function process(string $groupID = '', string $guidChar = '', int|string|null $processTV = ''): void
    {
        $processTV = (is_numeric($processTV) ? $processTV : Settings::settingValue('lookuptv'));
        if ($processTV > 0) {
            (new TVDB())->processSite($groupID, $guidChar, $processTV);
            (new TVMaze())->processSite($groupID, $guidChar, $processTV);
            (new TMDB())->processSite($groupID, $guidChar, $processTV);
            (new TraktTv())->processSite($groupID, $guidChar, $processTV);
        }
    }
}

