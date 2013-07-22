<?php

/*
 * This script resets the relnamestatus to 1 on every release that has relnamestatus 2, so you can rerun fixReleaseNames.php
 */
//This script is adapted from nZEDb

require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");

$db = new DB();

$res = $db->queryDirect("update releases set relnamestatus = 1 where relnamestatus = 2");

echo "Succesfully reset the relnamestatus of the releases to 1.\n";


?>
