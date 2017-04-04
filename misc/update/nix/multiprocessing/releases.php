<?php
declare(ticks=1);
require __DIR__ . DIRECTORY_SEPARATOR . '.do_not_run/require.php';

use nntmux\libraries\Forking;

(new Forking())->processWorkType('releases');
