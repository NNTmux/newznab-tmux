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

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupbooks') !== 0) {
            (new Books())->processBookReleases();
        }
    }
}

