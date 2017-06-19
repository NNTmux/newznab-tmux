<?php
require_once __DIR__ . '/app/config/bootstrap.php';

use app\extensions\command\NewUpdate;
use nntmux\ColorCLI;

if (isset($argv[1], $argv[2]) && $argv[1] === 'update' && ($argv[2] === 'nntmux' || $argv[2] === 'db')) {

	$update = new NewUpdate();
	if ($argv[2] === 'nntmux') {

		$update->nntmux();
	}

	if ($argv[2] === 'db') {
		$update->db();
	}
} else {
	ColorCLI::doEcho(ColorCLI::error('You need to specify proper arguments (update + nntmux or db'));
}