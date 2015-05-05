<?php

require_once("config.php");

$releases = new Releases();
$releases->processReleases(1, 1, $groupName, $nntp, true);
