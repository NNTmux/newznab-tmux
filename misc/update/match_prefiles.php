<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use Blacklight\NameFixer;

if (empty($argv[1]) && ($argv[1] !== 'full' || ! is_numeric($argv[1]))) {
    exit(
        (new ColorCLI)->error(
            PHP_EOL
            .'This script tries to match release filenames to PreDB filenames.'.PHP_EOL
            .'To display the changes, use "show" as the second argument. The optional third argument will limit the amount of filenames to attempt to match.'.PHP_EOL.PHP_EOL
            .'php match_prefiles.php full show	...: to run on full database and show renames.'.PHP_EOL
            .'php match_prefiles.php 2000 show	...: to run against 2000 distinct releases and show renames.'.PHP_EOL
        )
    );
}

try {
    $nameFixer = new NameFixer;
} catch (Exception $e) {
    echo $e->getMessage();
}

try {
    $nameFixer->getPreFileNames($argv);
} catch (Exception $e) {
    echo $e->getMessage();
}
