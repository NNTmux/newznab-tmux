<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Configuration\ConfigurationProvider;

class BooksProcessor
{
    /** @phpstan-ignore property.onlyWritten */
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(string $groupID = '', string $guidChar = ''): void
    {
        if ((int) app(ConfigurationProvider::class)->metadata()->bookLookup->value !== 0) {
            (new BookService)->processBookReleases($groupID, $guidChar);
        }
    }
}
