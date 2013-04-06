<?php
require(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/db.php");

$db = new DB;

$rel = $db->query("update releases set passwordstatus = -1 where rarinnerfilecount = 0");

?>
