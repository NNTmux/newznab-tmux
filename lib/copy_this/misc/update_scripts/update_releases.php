<?php

require_once("config.php");
use newznab\processing\ProcessReleases;

$releases = new ProcessReleases();
$sphinx = new Sphinx();
$releases->processReleases(1, 1, $groupName, $nntp, true);
$sphinx->update();
