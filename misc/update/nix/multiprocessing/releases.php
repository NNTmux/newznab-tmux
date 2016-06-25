<?php
declare(ticks=1);
require('.do_not_run/require.php');

use nntmux\libraries\Forking;

(new Forking())->processWorkType('releases');