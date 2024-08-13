<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\libraries\Forking;

try {
    (new Forking)->processWorkType('releases');
} catch (Exception $e) {
    echo $e->getMessage();
}
