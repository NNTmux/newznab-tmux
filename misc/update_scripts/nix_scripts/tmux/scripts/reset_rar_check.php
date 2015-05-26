<?php
require(dirname(__FILE__) . "/../bin/config.php");

$db = new newznab\db\Settings();

$rel = $db->query("update releases set passwordstatus = -1 where rarinnerfilecount = 0");
