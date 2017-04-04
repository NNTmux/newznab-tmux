<?php
declare(ticks=1);
require __DIR__ . DIRECTORY_SEPARATOR . '.do_not_run/require.php';

use \nntmux\libraries\Forking;

// This is the same as the python update_threaded.php
(new Forking())->processWorkType('update_per_group');
