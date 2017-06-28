#!/usr/bin/env php
<?php
require_once __DIR__ . '/app/config/bootstrap.php';

use app\extensions\command\Update;
use app\extensions\command\Verify;
use app\extensions\command\Version;

$update = new Update();
$verify = new Verify();

if (isset($argv[1]) && ($argv[1] === 'update' || $argv[1] === 'verify' || $argv[1] === 'version')) {
    if ($argv[1] === 'update') {
        if (isset($argv[2])) {
			if ($argv[2] === 'nntmux') {
				$update->nntmux();
			}

			if ($argv[2] === 'db') {
				$update->db();
			}
		} else {
			$update->error($update->getHelp());
		}
	}
	if ($argv[1] === 'verify') {
        $verify->settingstable();
    }
    if ($argv[1] === 'version') {
		$version = new Version();
        if (isset($argv[2]) && in_array($argv[2], ['all', 'sql', 'git', 'branch'], false)) {
            if ($argv[2] === 'all') {
				$version->all();
			}else if ($argv[2] === 'sql') {
				$version->sql();
			} else if ($argv[2] === 'git') {
				$version->git();
			} else if ($argv[2] === 'branch') {
				$version->branch();
			} else {
				$version->error($version->getHelp());
			}
        } else {
			$version->error($version->getHelp());
		}
    }
} else {
	$update->error($update->getHelp());
}