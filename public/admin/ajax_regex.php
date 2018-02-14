<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Regexes;
use Blacklight\Binaries;

// Login Check
$admin = new AdminPage;

if (! isset($_GET['action'])) {
    exit();
}

switch ($_GET['action']) {
	case 1:
		$id = (int) $_GET['col_id'];
		(new Regexes(['Settings' => $admin->settings]))->deleteRegex($id);
		echo "Regex $id deleted.";
		break;

	case 2:
		$id = (int) $_GET['bin_id'];
		(new Binaries(['Settings' => $admin->settings]))->deleteBlacklist($id);
		echo "Blacklist $id deleted.";
		break;
}
