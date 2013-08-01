<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once("consoletools.php");

$consoletools = new consoleTools();
if (isset($argv[1]))
	$consoletools->showsleep($argv[1]);
