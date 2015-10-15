<?php
require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\Sites;

// login check
$admin = new AdminPage;
$s = new Sites();

if (isset($_GET['action']))
{
	if ($_GET['action'] == "1")
		$s->updateItem("showadminwelcome", 1);
	else
		$s->updateItem("showadminwelcome", 0);
}

