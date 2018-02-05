<?php

use App\Models\Predb;

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

Predb::checkPre((isset($argv[1]) && is_numeric($argv[1]) ? $argv[1] : false));
