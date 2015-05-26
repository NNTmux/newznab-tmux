<?php
declare(ticks=1);
require('.do_not_run/require.php');

use \newznab\libraries\Forking;

(new Forking())->processWorkType('request_id');