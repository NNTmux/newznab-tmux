<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\PreDB;

$p = new PreDB(true);
$p->nzpreUpdate();
$p->processReleases(2000);
