<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Configuration\ConfigurationProvider;

class GamesProcessor
{
    /** @phpstan-ignore property.onlyWritten */
    private bool $echooutput;

    private GamesService $gamesService;

    public function __construct(bool $echooutput, ?GamesService $gamesService = null)
    {
        $this->echooutput = $echooutput;
        $this->gamesService = $gamesService ?? new GamesService;
    }

    public function process(string $groupID = '', string $guidChar = ''): void
    {
        if ((int) app(ConfigurationProvider::class)->metadata()->gameLookup->value !== 0) {
            $this->gamesService->processGamesReleases($groupID, $guidChar);
        }
    }
}
