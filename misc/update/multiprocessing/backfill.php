<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';
use Blacklight\libraries\Forking;
use Illuminate\Support\Facades\Log;

// Check if argument 1 is numeric, which is to limit article count.
try {
    (new Forking)->processWorkType('backfill', (isset($argv[1]) && is_numeric($argv[1]) && $argv[1] > 0 ? [0 => $argv[1]] : [0 => false]));
} catch (Exception $e) {
    Log::error($e->getTraceAsString());
    echo $e->getMessage();
}
