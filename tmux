#!/usr/bin/env php
<?php
require_once __DIR__ . '/app/config/bootstrap.php';

use app\extensions\command\Update;

$update = new Update();

if (isset($argv[1], $argv[2]) && $argv[1] === 'update' && ($argv[2] === 'nntmux' || $argv[2] === 'db')) {
	if ($argv[2] === 'nntmux') {
		$update->nntmux();
	}

	if ($argv[2] === 'db') {
		$update->db();
	}
} else {
	$update->error($update->getHelp());
}