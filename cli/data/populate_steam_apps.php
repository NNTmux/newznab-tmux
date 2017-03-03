<?php
/**
 * Created by PhpStorm.
 * User: darius
 * Date: 3.2.17.
 * Time: 22.25
 */

require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\Steam;

$steam = new Steam();

$steam->populateSteamAppsTable();