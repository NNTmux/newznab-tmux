<?php
require_once("config.php");
use newznab\PreDB;

$p = new PreDB(true);
$p->nzpreUpdate();
if(isset($argv[1]) && $argv[1] == true)
	$p->processReleases($daysback=(isset($argv[2]) ? $argv[2] : 3));
