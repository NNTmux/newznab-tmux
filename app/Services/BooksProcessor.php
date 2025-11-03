<?php

namespace App\Services;

use App\Models\Settings;
use Blacklight\Books;

class BooksProcessor
{
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(string $groupID = '', string $guidChar = ''): void
    {
        if ((int) Settings::settingValue('lookupbooks') !== 0) {
            (new Books)->processBookReleases($groupID, $guidChar);
        }
    }
}
