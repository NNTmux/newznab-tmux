<?php
require(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/db.php");

$db = new DB;

$rel = $db->query("truncate table parts");
$rel = $db->query("truncate table partsrepair");
$rel = $db->query("truncate table binaries;");

?>
