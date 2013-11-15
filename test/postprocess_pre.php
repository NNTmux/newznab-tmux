<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."lib/nntp.php");
require_once("prehash.php");
require_once("ColorCLI.php");

$nntp = new Nntp();
if ($nntp->doConnect() === false)
{
	$c = new ColorCLI;
	echo $c->error("Unable to connect to usenet.\n");
	return;
}

$predb = new Predb (true);
$predb->combinePre($nntp);
$nntp->doQuit();
