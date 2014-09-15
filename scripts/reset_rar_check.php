<?php
require(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/framework/Settings.php");

$db = new Settings();

$rel = $db->query("update releases set passwordstatus = -1 where rarinnerfilecount = 0");
