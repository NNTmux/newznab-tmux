<?php
declare(ticks=1);
require('.do_not_run/require.php');
require_once('Forking.php');
(new \Forking())->processWorkType('releases');