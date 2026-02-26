<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;

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
        if ((int) Settings::settingValue('lookupbooks') !== 0) {
            (new BookService)->processBookReleases($groupID, $guidChar);
        }
    }
}
