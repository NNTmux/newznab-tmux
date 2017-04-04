<?php
if (!isset($argv[1]) || !in_array($argv[1], ['backfill', 'binaries'])) {
	exit(
		'First argument (mandatory):' . PHP_EOL .
		'binaries => Do Safe Binaries update.' . PHP_EOL .
		'backfill => Do Safe Backfill update.' . PHP_EOL
	);
}

declare(ticks=1);
require __DIR__ . DIRECTORY_SEPARATOR . '.do_not_run/require.php';

use \nntmux\libraries\Forking;

(new Forking())->processWorkType('safe_' . $argv[1]);
