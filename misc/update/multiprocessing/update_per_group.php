<?php

declare(ticks=1);
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\libraries\Forking;

// This is the same as the python update_threaded.php
(new Forking())->processWorkType('update_per_group');
