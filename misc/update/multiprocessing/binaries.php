<?php

if (! isset($argv[1]) || ! is_numeric($argv[1])) {
    exit(
        'Argument 1 => (Number) Set to 0 to ignore, else fetches up to x new headers for every active group.'.PHP_EOL
    );
}
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';
use Blacklight\libraries\Forking;

try {
    (new Forking)->processWorkType('binaries', [0 => $argv[1]]);
} catch (Exception $e) {
    echo $e->getMessage();
}
