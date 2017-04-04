<?php
if (!isset($argv[1]) || !is_numeric($argv[1])) {
	exit(
		'Argument 1 => (Number) Set to 0 to ignore, else fetches up to x new headers for every active group.' . PHP_EOL
	);
}
declare(ticks=1);
require __DIR__ . DIRECTORY_SEPARATOR . '.do_not_run/require.php';
use nntmux\libraries\Forking;
(new Forking())->processWorkType('binaries', array(0 => $argv[1]));
