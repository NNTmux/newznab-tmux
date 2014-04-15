<?php
require(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");

$db = new DB;

$arr = array("parts", "partrepair", "binaries");

foreach ($arr as &$value) {
	$rel = $db->query("truncate table $value");
	printf("Truncating $value completed.\n");
}
unset($value);
