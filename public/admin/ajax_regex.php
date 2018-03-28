<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Regexes;
use Blacklight\Binaries;
use Blacklight\http\BasePage;

$page = new BasePage();

if (! request()->has('action')) {
    exit();
}

switch (request()->input('action')) {
    case 1:
        $id = (int) request()->input('col_id');
        (new Regexes(['Settings' => $page->settings]))->deleteRegex($id);
        echo "Regex $id deleted.";
        break;

    case 2:
        $id = (int) request()->input('bin_id');
        (new Binaries(['Settings' => $page->settings]))->deleteBlacklist($id);
        echo "Blacklist $id deleted.";
        break;
}
