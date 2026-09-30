<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Configuration\ConfigurationProvider;

class ConsolesProcessor
{
    /** @phpstan-ignore property.onlyWritten */
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(string $groupID = '', string $guidChar = ''): void
    {
        if ((int) app(ConfigurationProvider::class)->metadata()->gameLookup->value !== 0) {
            (new ConsoleService)->processConsoleReleases($groupID, $guidChar);
        }
    }
}
