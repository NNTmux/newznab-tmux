<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap.php';

use nntmux\PreDb;

(new PreDb(['Echo' => true]))->checkPre((isset($argv[1]) && is_numeric($argv[1]) ? $argv[1] : false));
