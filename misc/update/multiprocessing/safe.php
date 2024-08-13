<?php

if (! isset($argv[1]) || ! in_array($argv[1], ['backfill', 'binaries'])) {
    exit(
        'First argument (mandatory):'.PHP_EOL.
        'binaries => Do Safe Binaries update.'.PHP_EOL.
        'backfill => Do Safe Backfill update.'.PHP_EOL
    );
}

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\libraries\Forking;

try {
    (new Forking)->processWorkType('safe_'.$argv[1]);
} catch (Exception $e) {
    echo $e->getMessage();
}
