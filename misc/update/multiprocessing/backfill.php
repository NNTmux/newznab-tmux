<?php

declare(ticks=1);
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap.php';
use nntmux\libraries\Forking;

// Check if argument 1 is numeric, which is to limit article count.
(new Forking())->processWorkType(
    'backfill',
    (isset($argv[1]) && is_numeric($argv[1]) && $argv[1] > 0 ? [0 => $argv[1]] : [0 => false])
);
