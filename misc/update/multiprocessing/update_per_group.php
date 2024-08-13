<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\libraries\Forking;

try {
    (new Forking)->processWorkType('update_per_group');
} catch (Exception $e) {
    echo $e->getMessage();
}
