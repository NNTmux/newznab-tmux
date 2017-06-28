#!/usr/bin/env php
<?php
require_once __DIR__ . '/app/config/bootstrap.php';

use app\extensions\command\Update;
use app\extensions\command\Verify;

$update = new Update();
$verify = new Verify();

if (isset($argv[1]) && ($argv[1] === 'update' || $argv[1] === 'verify')) {
    if ($argv[1] === 'update') {
		if ($argv[2] === 'nntmux') {
			$update->nntmux();
		}

		if ($argv[2] === 'db') {
			$update->db();
		}
	}
	if ($argv[1] === 'verify') {
        $verify->settingstable();
    }
} else {
	$update->error($update->getHelp());
}