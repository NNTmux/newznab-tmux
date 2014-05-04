<?php

require(dirname(__FILE__) . "/config.php");
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/sphinx.php");

//$releases = new Releases;
$sphinx = new Sphinx();
//$releases->processReleases();
$sphinx->update();

