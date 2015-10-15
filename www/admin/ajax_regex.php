<?php
require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\Regexes;
use newznab\controllers\Binaries;

// Login Check
$admin = new AdminPage;

if (!isset($_GET['action'])) {
	exit();
}

switch($_GET['action']) {
	case 1:
		$id = (int) $_GET['col_id'];
		(new Regexes(['Settings' => $admin->settings]))->deleteRegex($id);
		print "Regex $id deleted.";
		break;

	case 2:
		$id = (int) $_GET['bin_id'];
		(new Binaries(['Settings' => $admin->settings]))->deleteBlacklist($id);
		print "Blacklist $id deleted.";
		break;
}