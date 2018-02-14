<?php

// Test the cache server connection.
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\libraries\Cache;

try {
    $cache = new Cache();
} catch (\Exception $error) {
    exit($error->getMessage().PHP_EOL);
}

print_r($cache->serverStatistics());
