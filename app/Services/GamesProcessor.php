<?php

namespace App\Services;

use App\Models\Settings;

class GamesProcessor
{
    private bool $echooutput;

    private GamesService $gamesService;

    public function __construct(bool $echooutput, ?GamesService $gamesService = null)
    {
        $this->echooutput = $echooutput;
        $this->gamesService = $gamesService ?? new GamesService;
    }

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupgames') !== 0) {
            $this->gamesService->processGamesReleases();
        }
    }
}
