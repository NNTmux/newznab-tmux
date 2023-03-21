<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ManticoreSearch;

try {
    (new ManticoreSearch())->optimizeRTIndex();
} catch (Exception $e) {
    echo $e->getMessage();
}
