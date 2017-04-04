<?php
declare(ticks=1);
require __DIR__ . '.do_not_run/require.php';

use \nntmux\libraries\Forking;

(new Forking())->processWorkType('request_id');
