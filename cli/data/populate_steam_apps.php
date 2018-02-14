<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Steam;

$steam = new Steam();

$steam->populateSteamAppsTable();
