<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\SphinxSearch;

try {
    (new SphinxSearch())->optimizeRTIndex();
} catch (Exception $e) {
    echo $e->getMessage();
}
