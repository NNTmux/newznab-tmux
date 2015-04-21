<?php

require("config.php");

$releases = new Releases;
$sphinx = new Sphinx();
$releases->processReleases();
$sphinx->update();
